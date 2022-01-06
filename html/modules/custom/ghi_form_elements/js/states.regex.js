(function (Drupal) {

  // Add regex support to the states API, see
  // https://evolvingweb.ca/blog/extending-form-api-states-regular-expressions
  Drupal.hpc_content_panes_states_extension = function(reference, value) {
    if ('regex' in reference) {
      return (new RegExp(reference.regex, reference.flags)).test(value);
    } else {
      return reference.indexOf(value) !== false;
    }
  }
  Drupal.behaviors.statesModification = {
    attach: function(context, settings) {
      if (Drupal.states) {
        Drupal.states.Dependent.comparisons.Object = Drupal.hpc_content_panes_states_extension;
      }
    }
  }
})(Drupal);