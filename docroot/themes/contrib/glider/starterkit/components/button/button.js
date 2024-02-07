((Drupal) => {
  Drupal.behaviors.buttons = {
    attach (context) {
      console.log('Hello, button!', context);
    },
    detach () {
    },
  };
})(Drupal);
