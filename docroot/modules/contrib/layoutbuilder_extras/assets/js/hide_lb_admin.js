(function (Drupal) {

  Drupal.behaviors.layoutBuilderExtrasDisableLBPreview = {
    attach: function attach(context) {

      const classesToToggle = [
        '.layout-builder__add-section',
        '.layout-builder__link',
      ];

      let disableLBPreviewBtn = document.getElementById('hide_lb_admin');
      if (disableLBPreviewBtn) {
        disableLBPreviewBtn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();

          let display = 'inline-block';
          disableLBPreviewBtn.classList.toggle('is-active');

          if (disableLBPreviewBtn.classList.contains('is-active')) {
            display = 'none';
          }

          classesToToggle.forEach(element =>
            document.querySelectorAll(element).forEach(element =>
              element.style.display = display
            )
          );

          document.querySelectorAll('.layout-builder__region').forEach(element => element.classList.toggle('hide-lb-admin'));
          document.querySelector('.layout-builder').classList.toggle('hide-lb-admin');
        });
      }

    }
  };


})(Drupal);
