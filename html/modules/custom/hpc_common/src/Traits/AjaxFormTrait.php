<?php

namespace Drupal\hpc_common\Traits;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Better support AJAX functionality in our custom plugin forms.
 */
trait AjaxFormTrait {

  /**
   * Se a form ID that is compatible with AJAX.
   */
  public function setAjaxCompatibleFormId(&$form, FormStateInterface $form_state) {
    if ($this->isAjax()) {
      // @todo static::ajaxSubmit() requires data-drupal-selector to be the same
      //   between the various Ajax requests. A bug in
      //   \Drupal\Core\Form\FormBuilder prevents that from happening unless
      //   $form['#id'] is also the same. Normally, #id is set to a unique HTML
      //   ID via Html::getUniqueId(), but here we bypass that in order to work
      //   around the data-drupal-selector bug. This is okay so long as we
      //   assume that this form only ever occurs once on a page. Remove this
      //   workaround in https://www.drupal.org/node/2897377.
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    }
  }

  /**
   * Generic ajax callback for comment forms.
   */
  public static function ajaxCancel(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand());
    return $response;
  }

}
