<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Helper class for common logic.
 */
class CommonHelper {

  /**
   * Calculate the ratio of two values.
   *
   * Round the value to 3 decimal places by default.
   */
  public static function calculateRatio($value1, $value2, $round = 3) {
    $ratio = !empty($value2) ? $value1 / $value2 : 0;
    return round($ratio, $round);
  }

  /**
   * Test if the given item can safely be cast to a string.
   *
   * @param mixed $item
   *   The item to check.
   *
   * @return bool
   *   Whether the item can be cast to string or not.
   */
  public static function canBeCastToString($item) {
    $is_array = is_array($item);
    $is_castable_object = is_object($item) && method_exists($item, '__toString');
    $is_convertible_to_string = !is_object($item) && settype($item, 'string') !== FALSE;
    return !$is_array && ($is_castable_object || $is_convertible_to_string);
  }

  /**
   * Check if the current request is ajax.
   *
   * @return bool
   *   True for ajax requests, FALSE for non-ajax requests.
   */
  public static function isAjaxRequest() {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Helper function to do a redirect.
   *
   * @param string $uri
   *   The URI to redirect to.
   * @param array $query_args
   *   The query arguments to add.
   * @param int $status
   *   The redirect HTTP status.
   * @param array $headers
   *   Optional headers for the redirect response.
   */
  public static function redirect($uri, array $query_args, $status = 302, array $headers = []) {
    $options = [];
    if (!empty($query_args)) {
      $options['query'] = $query_args;
    }
    $url = Url::fromUserInput($uri, $options)->toString();

    // Taken from https://www.drupal.org/node/2023537
    $response = new RedirectResponse($url, $status, $headers);
    $request = \Drupal::request();
    // Save the session so things like messages get saved.
    if ($request->getSession()->isStarted()) {
      $request->getSession()->save();
    }
    $response->prepare($request);
    // Make sure to trigger kernel events.
    \Drupal::service('kernel')->terminate($request, $response);
    $response->send();
    die();
  }

  /**
   * Remove diacritics from the given string.
   *
   * Taken from https://www.pontikis.net/tip/?id=22
   */
  public static function removeDiacritics($string) {
    $a = explode(',', 'À,Á,Â,Ã,Ä,Å,Æ,Ç,È,É,Ê,Ë,Ì,Í,Î,Ï,Ð,Ñ,Ò,Ó,Ô,Õ,Ö,Ø,Ù,Ú,Û,Ü,Ý,ß,à,á,â,ã,ä,å,æ,ç,è,é,ê,ë,ì,í,î,ï,ñ,ò,ó,ô,õ,ö,ø,ù,ú,û,ü,ý,ÿ,Ā,ā,Ă,ă,Ą,ą,Ć,ć,Ĉ,ĉ,Ċ,ċ,Č,č,Ď,ď,Đ,đ,Ē,ē,Ĕ,ĕ,Ė,ė,Ę,ę,Ě,ě,Ĝ,ĝ,Ğ,ğ,Ġ,ġ,Ģ,ģ,Ĥ,ĥ,Ħ,ħ,Ĩ,ĩ,Ī,ī,Ĭ,ĭ,Į,į,İ,ı,Ĳ,ĳ,Ĵ,ĵ,Ķ,ķ,Ĺ,ĺ,Ļ,ļ,Ľ,ľ,Ŀ,ŀ,Ł,ł,Ń,ń,Ņ,ņ,Ň,ň,ŉ,Ō,ō,Ŏ,ŏ,Ő,ő,Œ,œ,Ŕ,ŕ,Ŗ,ŗ,Ř,ř,Ś,ś,Ŝ,ŝ,Ş,ş,Š,š,Ţ,ţ,Ť,ť,Ŧ,ŧ,Ũ,ũ,Ū,ū,Ŭ,ŭ,Ů,ů,Ű,ű,Ų,ų,Ŵ,ŵ,Ŷ,ŷ,Ÿ,Ź,ź,Ż,ż,Ž,ž,ſ,ƒ,Ơ,ơ,Ư,ư,Ǎ,ǎ,Ǐ,ǐ,Ǒ,ǒ,Ǔ,ǔ,Ǖ,ǖ,Ǘ,ǘ,Ǚ,ǚ,Ǜ,ǜ,Ǻ,ǻ,Ǽ,ǽ,Ǿ,ǿ,Ά,ά,Έ,έ,Ό,ό,Ώ,ώ,Ί,ί,ϊ,ΐ,Ύ,ύ,ϋ,ΰ,Ή,ή');
    $b = explode(',', 'A,A,A,A,A,A,AE,C,E,E,E,E,I,I,I,I,D,N,O,O,O,O,O,O,U,U,U,U,Y,s,a,a,a,a,a,a,ae,c,e,e,e,e,i,i,i,i,n,o,o,o,o,o,o,u,u,u,u,y,y,A,a,A,a,A,a,C,c,C,c,C,c,C,c,D,d,D,d,E,e,E,e,E,e,E,e,E,e,G,g,G,g,G,g,G,g,H,h,H,h,I,i,I,i,I,i,I,i,I,i,IJ,ij,J,j,K,k,L,l,L,l,L,l,L,l,l,l,N,n,N,n,N,n,n,O,o,O,o,O,o,OE,oe,R,r,R,r,R,r,S,s,S,s,S,s,S,s,T,t,T,t,T,t,U,u,U,u,U,u,U,u,U,u,U,u,W,w,Y,y,Y,Z,z,Z,z,Z,z,s,f,O,o,U,u,A,a,I,i,O,o,U,u,U,u,U,u,U,u,U,u,A,a,AE,ae,O,o,Α,α,Ε,ε,Ο,ο,Ω,ω,Ι,ι,ι,ι,Υ,υ,υ,υ,Η,η');
    return str_replace($a, $b, $string);
  }

  /**
   * Sanitize the given label.
   */
  public static function sanitizeLabel($label) {
    return Html::escape($label);
  }

  /**
   * Sanitize a string to use as a display key.
   *
   * That can be necessary in different conditions, e.g.:
   * - for use as an API group by parameter
   * - for use as a filename for download files
   * .
   *
   * @param string $string
   *   The input string.
   *
   * @return string
   *   The sanitized string.
   */
  public static function sanitizeDisplayKey($string) {
    return preg_replace("/[^A-Za-z0-9 ]/", '', str_replace([' ', '(', ')'], '', self::removeDiacritics($string)));
  }

  /**
   * Create a destination path.
   *
   * Parse the current destination and replace the argument
   * for the given index. Any active query string will be
   * maintained.
   */
  public static function replaceInUrl($url, $replacements) {
    // Parse the current destination URL.
    $url_parts = UrlHelper::parse(trim($url, '/'));
    $url_args = explode('/', $url_parts['path']);

    // Set the new argument to the one passed in.
    foreach ($replacements as $index => $arg) {
      if (empty($arg)) {
        unset($url_args[$index]);
      }
      else {
        $url_args[$index] = $arg;
      }
    }

    // Construct a new destination path with the new year argument.
    $new_path = implode('/', $url_args);

    // Always return to the first page of the results.
    $query = $url_parts['query'];
    if (!empty($query['page'])) {
      unset($query['page']);
    }

    // Unset any active sort query parameters, as they
    // may not be valid on the destination page.
    if (!empty($query['sort'])) {
      unset($query['sort']);
    }

    // Unset any active order query parameters, as they
    // may not be valid on the destination page.
    if (!empty($query['order'])) {
      unset($query['order']);
    }

    return [
      'path'  => $new_path,
      'query' => $query,
    ];
  }

}
