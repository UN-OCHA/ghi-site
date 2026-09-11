<?php

namespace Drupal\ghi_blocks\Interfaces;

use Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface;

/**
 * Identifies items whose values belong to the attachment in the row context.
 *
 * These items render on attachment rows and require an attachment source when
 * used in a mixed table. Items that independently query attachments for an
 * entity-level value do not implement this interface.
 */
interface AttachmentContextItemInterface extends ConfigurationContainerItemPluginInterface {}
