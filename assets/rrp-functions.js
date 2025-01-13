jQuery(document).ready(function($) {
  var image = document.getElementById('rrp-crop-image');

  $(image).on('load', function() {
    // Check if Cropper library is loaded correctly
    if (typeof Cropper === 'undefined') {
      console.error("Cropper library could not be loaded!");
      return;
    }

    var cropper = new Cropper(image, {
      aspectRatio: NaN,
      viewMode: 1,
      autoCrop: false,
      dragMode: 'none',
      cropBoxResizable: false,
      cropBoxMovable: true,
      zoomable: false,
      scalable: false,
      mouseWheelZoom: false,
      ready: function() {
        cropper.clear(); // Hide the crop area initially
      }
    });

    function setCropBoxBasedOnInput() {
      var width = parseFloat($('#rrp_width').val());
      var height = parseFloat($('#rrp_height').val());

      if (width >= 10 && height >= 10) { // Must be at least 2 digits
        var cmToPx = 10; // Conversion rate from cm to px
        var cropWidth = width * cmToPx;
        var cropHeight = height * cmToPx;

        var cropBoxData = {};

        if (cropWidth > cropHeight) {
          cropBoxData.width = image.naturalWidth;
          cropBoxData.height = (cropHeight / cropWidth) * image.naturalWidth;
          cropBoxData.top = (image.naturalHeight - cropBoxData.height) / 2;
        } else {
          cropBoxData.height = image.naturalHeight;
          cropBoxData.width = (cropWidth / cropHeight) * image.naturalHeight;
          cropBoxData.left = (image.naturalWidth - cropBoxData.width) / 2;
        }

        cropper.setCropBoxData(cropBoxData);
        cropper.crop(); // Make the crop area visible

        // Display the area in square meters
        var area = (width * height) / 10000; // Convert cm² to m²
        $('#crop-area-display').text('Crop Area: ' + area.toFixed(2) + ' m²');
      } else {
        cropper.clear(); // Clear the crop area for invalid inputs
        $('#crop-area-display').text('');
      }
    }

    setCropBoxBasedOnInput();

    $('#rrp_width, #rrp_height').on('input', function() {
      var value = $(this).val();
      if (value.length > 0 && value.length < 2) {
        $(this).next('.error-message').show(); // Show error message
      } else {
        $(this).next('.error-message').hide(); // Hide error message
        setCropBoxBasedOnInput(); // Repeat the scaling process as values change
      }
    });

    $('#confirm-crop').on('click', function() {
      var croppedData = cropper.getData();
      $('#rrp_crop_data').val(JSON.stringify(croppedData));

      cropper.getCroppedCanvas().toBlob(function(blob) {
        var formData = new FormData();
        formData.append('file', blob, 'cropped-image.jpg');
        formData.append('action', 'save_cropped_image');
        formData.append('security', ajax_obj.nonce);

        if (typeof ajax_obj === 'undefined') {
          console.error("ajax_obj is not defined!");
          return;
        }

        $.ajax({
          url: ajax_obj.ajaxurl,
          method: 'POST',
          data: formData,
          contentType: false,
          processData: false,
          success: function(response) {
            console.log('Successful response:', response);
            if (response.success) {
              alert('Image successfully cropped and uploaded!');
            } else {
              alert('Image could not be uploaded: ' + response.data);
            }
          },
          error: function(jqXHR, textStatus, errorThrown) {
            console.error("AJAX Error: ", textStatus, errorThrown);
            alert('An error occurred during image upload. Details: ' + errorThrown);
          }
        });
      });
    });
  });

  if (image.complete) {
    $(image).trigger('load');
  }

  $('#rrp_width, #rrp_height').on('input', function() {
    var value = parseFloat($(this).val());
    if (value < 0) {
      $(this).val(''); // Clear the field if a negative value is entered
      alert('Please enter a positive value.');
    }
  });
});
