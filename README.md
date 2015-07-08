# Kevlar v1.0.0

Kevlar is a highly customizable caching system for Magento Enterprise Edition. Full parallel support is offered for Akamai, CloudFlare and Varnish nodes.

Designed as push only system that relies on high (cache) TTL values for maximum coverage and performance.

##### Highlights

  - Can support multiple providers at once w/ multiple domains and/or nodes.
  - Automatically detects and queues up category, product, inventory and CMS updates. Updates to products will trigger updates for categories the product is associated with (if product is visible). System will automatically detect `products per page` settings and purge accordingly.
  - Built-in cache warm-up. Kevlar can make a request to a changed asset behind the caching layer before requesting a purge.
  - Pre-generates thumbnails for product images before pushing updates to caching provider(s).
  - Smart delta based queue that leverages enterprise_logging_event_changes tables to skip unnecessary re-indexing of data.
  - Kevlar will detect / group duplicate updates and 'skip' them.
  - Extensive support for third party vendor API's. Built-in purge limit w/ sleep buffer. Retry system, etc.
  - Provides real ETA after purge request is made for Akamai and CloudFlare. Varnish ETA is based on average turn around time per node * number of nodes.
  - Full support for Magento installations w/ multiple stores.
  - Ability to do on-demand purge of URI assets via Magento Admin w/ an option to by-pass the queue and make a direct purge request (emergency mode) for instant results. Notification option is available (will send out a notification when emergency purge is requested).
  - Extremely flexible architecture for easy extensibility.

##### Kevlar Configuration 
High level overview of kevlar configuration. Currently configuration resides under `Kevlar/Core/etc/config.xml`. See to-do list at the bottom.

- indexBased
 - Tells Kevlar to generate and use proper deltas when re-generating indexes. This should probably be turned on.
- forceIndex
 - Kevlar will smartly re-index indexes that were changed. If you turn this off; you should update index settings within Magento to re-index on save. Not recommended.
- autoWarm
 - Kevlar will retrieve every asset that was flushed in Magento before requesting a cache purge. A must in specialized setups. Especially useful if your purge volume is low and you want end user to have the best experience (when request is un-cached via CDN the collection/etc caches will be warmed up for users node).
- log
 - Enables logging (var/log/kevlar.log under Magento)
- providers
 - Configuration for supported providers (Akamai, CloudFlare and Varnish). Full support for multiple domains is offered. For example, if you have m.domain.com and www.domain.com you may set the domains up under CloudFlare as two separate domains. Updates will trigger purge request on m. and www. domains. Varnish can support unlimited number of back-ends.

#####  Magento Configuration 

Following configuration changes must be made prior to using Kevlar. This is based on optimal configuration (with indexBased and forceIndex turned on).

- Index Settings:
 - Catalog Category/Product Index: Update when scheduled.
 - Category URL Rewrite: Update on Save.
 - Product URL Rewrite: Update on Save.
 - Redirect URL Rewrite: Update on Save.
 - Category Flat Index: Update when scheduled.
 - Catalog Search Index: Update when scheduled.
 - Product Flat Index: Update when scheduled.
 - Price and Stock Index: Update when scheduled.
- Cache Settings:
  - FPC (Full Page Caching) must be turned off.
  
##### Cronjobs
Kevlar will install two cron jobs: 
- One that processes the queue (*/15) named `kevlar_cache_queue`. If you are not using delta based approach; set this to run at a higher interval.
- One that will clear out processed items from the queue (30 2 * * *) named `kevlar_cache_queue_flush`.

##### Tables
Kevlar creates and leverages following tables:
- kevlar_cache_queue
- kevlar_cache_queue_flush

#####  To-do
- Move configuration under Magento Admin.
- Detect if we are flushing X amount of URI's and switch over to full site purge .vs link by link approach w/ CloudFlare setup. Purge end-point is already implemented. Just need to figure out how to approach this from architectural stand point.
- In this version, CMS forms will have a check-box that will trigger site wide cache flush. This should be a configuration setting that enables check boxes for certain CMS blocks. The process of purging all of the pages is slow, especially if provider does not support wild-card/full purge. You can easily map CMS blocks with code for now (as needed).
