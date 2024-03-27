<?php

namespace Drupal\ghi_content\Plugin\Field\FieldType;

/**
 * Plugin implementation of the 'ghi_remote_document' field type.
 *
 * @FieldType(
 *   id = "ghi_remote_document",
 *   label = @Translation("Remote document"),
 *   category = @Translation("GHI Fields"),
 *   default_widget = "ghi_remote_document",
 *   default_formatter = "ghi_remote_document",
 *   cardinality = 1
 * )
 */
class DocumentItem extends RemoteItemBase {

}
