<?php

namespace Drupal\ghi_content\Plugin\Field\FieldType;

/**
 * Plugin implementation of the 'ghi_remote_article' field type.
 *
 * @FieldType(
 *   id = "ghi_remote_article",
 *   label = @Translation("Remote article"),
 *   category = @Translation("GHI Fields"),
 *   default_widget = "ghi_remote_article",
 *   default_formatter = "ghi_remote_article",
 *   cardinality = 1
 * )
 */
class ArticleItem extends RemoteItemBase {

}
