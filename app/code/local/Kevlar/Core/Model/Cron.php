<?php
use \DateTime;
use \DateInterval;
use \Exception;
use \Mage;

/**
 * Cron model
 *
 * @author Aleksey Korzun <aleksey@baublebar.com>
 * @category Kevlar
 * @package Kevlar_Core
 * @copyright Copyright (c) 2015 BaubleBar Inc. (http://www.baublebar.com)
 */
class Kevlar_Core_Model_Cron extends Varien_Object
{
    /**
     * Failed status
     *
     * @var string
     */
    const STATUS_FAILED = 'failure';

    /**
     * Skipped status
     *
     * @var string
     */
    const STATUS_SKIPPED = 'skipped';

    /**
     * Re-queued status
     *
     * @var string
     */
    const STATUS_REQUEUED = 'queued';

    /**
     * Maximum number of URLS per flush request
     *
     * @var int
     */
    const PURGE_LIMIT = 10;

    /**
     * Maximum number of retries before skipping item
     *
     * @var int
     */
    const RETRY_LIMIT = 10;

    /**
     * Default rest period between API requests
     *
     * @var int
     */
    const REST_LIMIT = 180;

    /**
     * Instance of factory helper
     *
     * @var Kevlar_Core_Helper_Factory
     */
    protected $factory;

    /**
     * List of URLS that we need to flush
     *
     * @var string[]
     */
    protected $queue = array();

    /**
     * List of URLS that we need to re-queue
     *
     * @var string[]
     */
    protected $reQueue = array();

    /**
     * Log of URLS that we seen
     *
     * @var string[]
     */
    protected $log = array();

    /**
     * Instance of Magento cache
     *
     * @var object
     */
    protected static $cache;

    /**
     * Instance of read only database
     *
     * @var object
     */
    protected static $database;

    /**
     * Required indexes that we piggy back from as deltas
     *
     * @var string[]
     */
    protected $indexes = array(
        'catalog_category_product' => false,
        'cataloginventory_stock' => false,
        'catalog_product_price' => false,
        'catalog_product_flat' => false,
        'catalog_category_flat' => false,
        'catalog_url_category' => false
    );

    /**
     * List of triggers that identify catalog category product updates
     *
     * @var string[]
     */
    protected $triggers = array(
        'is_changed_categories',
        'is_changed_product_list'
    );

    /**
     * Constructor
     */
    public function _construct()
    {
        $this->factory = new Kevlar_Core_Helper_Factory();

        // Initialize
        self::$cache = Mage::app()->getCache();
        self::$database = Mage::getSingleton('core/resource');
    }

    /**
     * Flush state
     */
    protected function flush()
    {
        $this->log = array();
        $this->queue = array();
        $this->reQueue = array();
    }

    /**
     * Process queue
     *
     * @return bool
     */
    public function process()
    {
        // Verify that we have changes in our queue
        if (!$this->getIndexChanges()) {
            $this->factory->log(
                'No index changes detected, aborting'
            );
            return false;
        }

        // Establish index delta
        $delta = $this->getIndexDelta();
        if (!$delta) {
            $this->factory->log(
                'Could not obtain a delta, aborting'
            );
            return false;
        }

        $this->factory->log(
            'Delta is: ' . $delta->format('Y-m-d H:i:s')
        );

        foreach ($this->factory->workers() as $worker) {
            $this->flush();

            // Avoid problems caused by rate limiting
            $limit = $worker->getLimit();

            $collection = $worker->getPending();
            foreach ($collection as $item) {
                try {
                    $compare = new DateTime($item->getCreated());
                    if ($compare) {
                        if ($compare <= $delta) {
                            // Create new mini queue for this record
                            $this->queue[$item->getId()] = array();

                            $urls = (array)json_decode($item->getUrl());
                            if ($urls) {
                                $count = 0;

                                foreach ($urls as $url) {
                                    // Skip URL if we already seen it during this run
                                    if (in_array($url, $this->log)) {
                                        $this->factory->log(
                                            'Skipped duplicate: ' . $url
                                        );

                                        continue;
                                    }

                                    // Break the rest of URI's into another request
                                    if ($count > $limit) {
                                        if (!isset($this->reQueue[$item->getId()])) {
                                            $this->reQueue[$item->getId()] = array();
                                        }

                                        $this->reQueue[$item->getId()][] = $url;
                                        continue;
                                    }

                                    $this->queue[$item->getId()][] = $url;
                                    $this->log[] = $url;

                                    ++$count;
                                }
                            }

                            // Update item if part of it was re-queued
                            if (isset($this->reQueue[$item->getId()]) && isset($this->queue[$item->getId()])) {
                                $item->setUrl(json_encode($this->queue[$item->getId()]));
                                $item->save();
                            }
                        }
                    }
                } catch (Exception $exception) {
                }
            }

            // Re-queue
            if ($this->reQueue) {
                foreach ($this->reQueue as $urls) {
                    $urls = array_chunk($urls, $limit);

                    foreach ($urls as $url) {
                        $worker->setObjects($url);
                        $worker->queue();
                    }
                }
            }

            // Nothing is in purge queue, move on
            if (!$this->queue) {
                continue;
            }

            // Reset count
            $count = 0;

            foreach ($this->queue as $id => $urls) {
                // Purge in chunks
                $urls = array_chunk($urls, self::PURGE_LIMIT);

                // Retrieve item from collection
                $item = $collection->getItemById($id);

                if (!$urls) {
                    $item->setIsPending(0);
                    $item->save();

                    // Log this
                    $this->factory->log(
                        'Skipped item #' . $item->getId()
                    );

                    continue;
                }

                foreach ($urls as $url) {
                    // Take a break to avoid rate limiting
                    $count += count($url);
                    if (($count + self::PURGE_LIMIT) > $limit) {
                        $this->factory->log('Sleeping...');

                        sleep(self::REST_LIMIT);

                        $count = 0;

                        // Re-init database connection
                        $this->pingDb();
                    }

                    $worker->setObjects($url);

                    if (!$worker->purge()) {
                        // Check number of attempts and purge item if retry limit has been reached
                        $attempts = (int)$item->getAttempt();
                        if ($attempts >= self::RETRY_LIMIT) {
                            // Notify
                            $this->factory->notify(
                                'Failed to flush data from ' . ucfirst($worker->getType()),
                                implode(
                                    ', ',
                                    (array)json_decode($item->getUrl())
                                )
                            );

                            $this->factory->log('Fail to flush item #' . $item->getId());

                            $item->delete();
                        }

                        ++$attempts;

                        // Log this
                        $this->factory->log(
                            'Will retry item #' . $item->getId()
                        );

                        $item->setAttempt($attempts);
                        $item->save();

                        continue 2;
                    }
                }

                // This item is done, update and move on
                $eta = new DateTime();
                $eta->add(
                    new DateInterval('PT' . (int)$worker->getEstimate() . 'S')
                );

                $item->setEta($eta->format('Y-m-d H:i:s'));
                $item->setIsPending(0);
                $item->save();

                // Log this
                $this->factory->log(
                    'Processed item #' . $item->getId()
                );
            }
        }

        $this->factory->log('Completed');
    }

    /**
     * Clean up stale queue items
     *
     * @return bool
     */
    public function cleanUp()
    {
        foreach ($this->factory->workers() as $worker) {
            $collection = $worker->getProcessed();

            foreach ($collection as $item) {
                $item->delete();
            }
        }

        return true;
    }

    /**
     * Check if there are pending items in the flush queue
     *
     * @return int
     */
    protected function getIndexChanges()
    {
        $changes = 0;

        foreach ($this->factory->workers() as $worker) {
            $changes += (int)$worker->getPending()->count();
        }

        return $changes;
    }

    /**
     * Simulate database ping to prevent stale connections
     */
    protected function pingDb()
    {
        $links = array(
            self::$database->getConnection('core_read'),
            self::$database->getConnection('core_write')
        );

        foreach ($links as $link) {
            $link->closeConnection();
        }
    }

    /**
     * Check if there is a general product/category/cms update
     *
     * @return bool
     */
    protected function hasGeneralUpdate()
    {
        $key = md5(__METHOD__);

        $delta = self::$cache->load($key);
        if (!$delta) {
            $sql = "
              SELECT
                MAX(`id`) AS `delta`
              FROM
                `enterprise_logging_event_changes`";

            $result = self::$database
                ->getConnection('core_read')
                ->fetchRow($sql);

            if ($result && isset($result['delta'])) {
                self::$cache->save($result['delta'], $key);
            }

            return true;
        }

        $sql = "
          SELECT
            `id` AS `delta`
          FROM
            `enterprise_logging_event_changes`
          WHERE
            `source_name` IN ('Mage_Catalog_Model_Product','Mage_Catalog_Model_Category', 'Mage_Cms_Model_Block') AND
            `id` > {$delta}
          ORDER BY
            `id` DESC
          LIMIT 1";

        $result = self::$database
            ->getConnection('core_read')
            ->fetchRow($sql);

        if ($result && isset($result['delta'])) {
            self::$cache->save($result['delta'], $key);
            return true;
        }

        return false;
    }

    /**
     * Check if there is a new catalog category product update
     *
     * @return bool
     */
    protected function hasCatalogCategoryProductUpdate()
    {
        $key = md5(__METHOD__);

        $delta = self::$cache->load($key);
        if (!$delta) {
            $sql = "
              SELECT
                MAX(`id`) AS `delta`
              FROM
                `enterprise_logging_event_changes`";

            $result = self::$database
                ->getConnection('core_read')
                ->fetchRow($sql);

            if ($result && isset($result['delta'])) {
                self::$cache->save($result['delta'], $key);
            }

            return true;
        }

        $sql = "
          SELECT
            `id`, `result_data`
          FROM
            `enterprise_logging_event_changes`
          WHERE
            `source_name` IN ('Mage_Catalog_Model_Product','Mage_Catalog_Model_Category') AND
            `id` > {$delta}
          ORDER BY
            `id` DESC";

        $result = self::$database
            ->getConnection('core_read')
            ->fetchAll($sql);

        if ($result && isset($result[0]) && $result[0]['id']) {
            // Update delta
            self::$cache->save($result[0]['id'], $key);

            foreach ($result as $row) {
                $data = unserialize((string)$row['result_data']);
                if ($data) {
                    foreach ($this->triggers as $trigger) {
                        if (isset($data[$trigger]) && $data[$trigger] == 1) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Generate latest index delta
     *
     * @throws Exception
     * @return bool|DateTime
     */
    protected function getIndexDelta()
    {
        // Check if index approach is turned on, otherwise fake delta to now
        if ((string)$this->factory->configuration()->indexBased !== '1') {
            return new DateTime();
        }

        // Re-index on the fly
        if ((string)$this->factory->configuration()->forceIndex == '1') {
            // Push to temporary array used for re-indexing
            $forceIndex = $this->indexes;

            // Only re-index if there is an actual update
            if (!$this->hasGeneralUpdate()) {
                $this->factory->log(
                    'Skipping general re-indexing'
                );

                $forceIndex = array(
                    'catalog_category_product' => false
                );
            }

            if (!$this->hasCatalogCategoryProductUpdate()) {
                $this->factory->log(
                    'Skipping re-indexing of: catalog_category_product'
                );

                // No longer part of the delta calculation
                unset($this->indexes['catalog_category_product']);

                // Remove from re-indexing
                unset($forceIndex['catalog_category_product']);
            }

            if (!count($forceIndex)) {
                $delta = new DateTime();
                $delta->sub(
                    new DateInterval('PT5M')
                );

                return $delta;
            }

            $indexer = Mage::getModel('index/indexer');

            foreach (array_keys($forceIndex) as $index) {
                $this->factory->log('Started re-indexing: ' . $index);

                $process = $indexer->getProcessByCode($index);
                $process->reindexEverything();

                // Trigger index specific observer
                Mage::dispatchEvent(
                    $process->getIndexerCode() . '_shell_reindex_after'
                );

                $this->factory->log('Done');

                // Re-init database connection
                $this->pingDb();
            }

            // Trigger common index observer
            Mage::dispatchEvent('shell_reindex_finalize_process');
        }

        $indexCollection = Mage::getModel('index/process')->getCollection()
            ->addFilter('mode', 'real_time');

        if ($indexCollection) {
            foreach ($indexCollection as $index) {
                if (isset($this->indexes[$index->getIndexerCode()])) {
                    $this->indexes[$index->getIndexerCode()] = $index->getEndedAt();
                }
            }
        }

        $delta = false;

        // Loop thought required indexes and get the earliest execution time
        // this time will be used as our delta across all indexes
        foreach ($this->indexes as $index => $status) {
            if (!$status) {
                // Log this
                $this->factory->log(
                    'Required index ' . $index . ', was not found'
                );

                throw new Exception(
                    'Required index ' . $index . ', was not found'
                );
            }

            try {
                $status = new DateTime($status);
                if ($status) {
                    // Find earliest delta
                    if (!$delta || ($delta > $status)) {
                        $delta = $status;
                    }
                }
            } catch (Exception $exception) {
            }
        }

        return $delta;
    }
}
