# PHSA Migrations
These module compiles all the available migrations for PHSA.

## News Migration
To execute the news migration, run the following:
`ddev drush mim --tag=news`

This command will execute all the news related migration in order.

If you wish to do it one by one, follow this order:

1. Run the content crawling migration: `ddev drush mim node_news`
   1. This command will crawl the list of news and extract the data we need.
   2. It'll create the nodes, along with the content paragraphs, and media.
   3. This migration must be run first because it's used as the mapper for the other migrations.
2. Run the image media migration: `ddev drush mim image_news`
3. Run the embed videos migration: `ddev drush mim remote_video_news`

If you need to rollback any migration, you can run `ddev drush mr MIGRATION_ID`.

## Supporting paragraphs
Currently, the `node_news` migration creates paragraph when the migration is run. The `phsa_content_processor` source plugin supports the reverting of the paragraphs entities that are created. The `Drupal\phsa_migration\Plugin\PhsaContentProcessor` plugin crawls the pages and creates the paragraph. From the paragraph, a list of the `entity_id` and `revision_id` is returned.

To add support of new paragraphs, follow these references:
- To go through each DOM element and map it, you need to add your code in `Drupal\phsa_migration\Plugin\PhsaContentProcessor.php::processBodyContent`.
- The `Drupal\phsa_migration\Plugin\PhsaContentProcessor.php::createParagraphEntity` function is where you have to map the paragraph. In there, you will receive the DOM node from where to extract the data, and then create the paragraph.
- Then, you need to use `Drupal\phsa_migration\Plugin\PhsaContentProcessor::createDomContainerFromParagraph` to create a DOM node of type container that will be used to keep a control of the paragraphs and crawling data.
- Then, you will have to pass the DOM container to the anonymous function named `append_element()`.
- See an example in `Drupal\phsa_migration\Plugin\PhsaContentProcessor` line 201 (for processing an "Accordion" paragraph).
