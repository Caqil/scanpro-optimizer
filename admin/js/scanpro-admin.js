// admin/js/scanpro-admin.js
(function($) {
    'use strict';

    // Document ready
    $(function() {
        // API key validation
        $('#scanpro_validate_api_key').on('click', function() {
            var apiKey = $('#scanpro_api_key').val();
            
            if (!apiKey) {
                $('#scanpro_api_key_validation_result')
                    .removeClass('success')
                    .addClass('error')
                    .text(scanpro_params.i18n.api_validation_error);
                return;
            }
            
            // Disable button during validation
            $(this).prop('disabled', true).text('Validating...');
            
            $.ajax({
                url: scanpro_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'scanpro_validate_api_key',
                    nonce: scanpro_params.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $('#scanpro_api_key_validation_result')
                            .removeClass('error')
                            .addClass('success')
                            .text(response.data.message);
                    } else {
                        $('#scanpro_api_key_validation_result')
                            .removeClass('success')
                            .addClass('error')
                            .text(response.data.message);
                    }
                },
                error: function() {
                    $('#scanpro_api_key_validation_result')
                        .removeClass('success')
                        .addClass('error')
                        .text('Connection error. Please try again.');
                },
                complete: function() {
                    $('#scanpro_validate_api_key').prop('disabled', false).text('Validate Key');
                }
            });
        });
        
        // PDF conversion file selection
        $('.scanpro-file-input').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            
            if (fileName) {
                $(this).closest('.scanpro-form-group').find('.scanpro-selected-file').text(fileName);
            } else {
                $(this).closest('.scanpro-form-group').find('.scanpro-selected-file').text('');
            }
        });
        
       // PDF conversion form submission
$('.scanpro-pdf-form').on('submit', function(e) {
    e.preventDefault();
    
    var $form = $(this);
    var $submitButton = $form.find('.scanpro-convert-button');
    var $result = $form.find('.scanpro-conversion-result');
    var formData = new FormData(this);
    
    // Check if file is selected
    var fileInput = $form.find('input[type="file"]')[0];
    if (!fileInput.files.length) {
        $result
            .removeClass('success')
            .addClass('error')
            .text('Please select a file to convert.')
            .show();
        return;
    }
    
    // Check file size
    if (fileInput.files[0].size > 15 * 1024 * 1024) { // 15MB limit
        $result
            .removeClass('success')
            .addClass('error')
            .text('File is too large. Maximum file size is 15MB.')
            .show();
        return;
    }
    
    // Validate file extension
    var fileName = fileInput.files[0].name;
    var fileExt = fileName.split('.').pop().toLowerCase();
    var validExts = ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png'];
    
    if ($.inArray(fileExt, validExts) === -1) {
        $result
            .removeClass('success')
            .addClass('error')
            .text('Invalid file type. Allowed types: PDF, DOCX, XLSX, PPTX, JPG, PNG')
            .show();
        return;
    }
    
    // Check if converting to the same format
    var outputFormat = $form.data('output-format');
    if (fileExt === outputFormat) {
        $result
            .removeClass('success')
            .addClass('error')
            .text('Input and output formats cannot be the same')
            .show();
        return;
    }
    
    // Add action and nonce
    formData.append('action', 'scanpro_convert_pdf');
    formData.append('nonce', scanpro_params.nonce);
    formData.append('output_format', outputFormat);
    
    // Disable button during conversion
    $submitButton.prop('disabled', true).text('Converting...');
    $result.removeClass('success error').hide();
    
    // Show processing message
    $result
        .removeClass('success error')
        .text('Processing your file. This may take up to 2 minutes for large files...')
        .show();
    
    $.ajax({
        url: scanpro_params.ajax_url,
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        timeout: 180000, // 3 minute timeout
        success: function(response) {
            if (response.success) {
                $result
                    .removeClass('error')
                    .addClass('success')
                    .html(response.data.message + ' <a href="' + response.data.file_url + '" download="' + response.data.file_name + '" class="button">Download</a>')
                    .show();
                
                // Reset form
                $form[0].reset();
                $form.find('.scanpro-selected-file').text('');
            } else {
                $result
                    .removeClass('success')
                    .addClass('error')
                    .text(response.data.message)
                    .show();
            }
        },
        error: function(xhr, textStatus, errorThrown) {
            var errorMsg = 'Connection error. Please try again.';
            
            if (textStatus === 'timeout') {
                errorMsg = 'Request timed out. The file might be too large or the server is busy.';
            } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMsg = xhr.responseJSON.data.message;
            }
            
            $result
                .removeClass('success')
                .addClass('error')
                .text(errorMsg)
                .show();
        },
        complete: function() {
            $submitButton.prop('disabled', false).text($submitButton.data('original-text') || 'Convert');
        }
    });
});
        
        // Store original button text for conversion buttons
        $('.scanpro-convert-button').each(function() {
            $(this).data('original-text', $(this).text());
        });
        
       // Media library image optimization
$(document).on('click', '.scanpro-optimize-button, .scanpro-reoptimize-button', function() {
    var $button = $(this);
    var attachmentId = $button.data('id');
    var $status = $button.siblings('.scanpro-optimize-status');
    
    // Disable button during optimization
    $button.prop('disabled', true).text('Optimizing...');
    
    // Show processing message
    $status.text('Processing. This may take a minute...').removeClass('error');
    
    $.ajax({
        url: scanpro_params.ajax_url,
        type: 'POST',
        data: {
            action: 'scanpro_compress_image',
            nonce: scanpro_params.nonce,
            attachment_id: attachmentId
        },
        timeout: 120000, // 2 minute timeout
        success: function(response) {
            if (response.success) {
                // Update UI with optimization results
                var html = '<div class="scanpro-optimized-info">' +
                    '<div class="scanpro-optimization-badge">' +
                    '<span class="dashicons dashicons-yes-alt"></span> Optimized' +
                    '</div>' +
                    '<div class="scanpro-optimization-details">' +
                    '<div>Saved: ' + response.data.saved + '</div>' +
                    '<div>Reduced: ' + response.data.compressed_size + ' (' + response.data.savings_percentage + '%)</div>' +
                    '</div>' +
                    '<button type="button" class="button scanpro-reoptimize-button" data-id="' + attachmentId + '">Re-optimize</button>' +
                    '</div>';
                
                $button.closest('td').html(html);
            } else {
                $status.text(response.data.message).addClass('error');
                $button.prop('disabled', false).text('Optimize');
            }
        },
        error: function(xhr, textStatus, errorThrown) {
            var errorMsg = 'Connection error. Please try again.';
            
            if (textStatus === 'timeout') {
                errorMsg = 'Request timed out. The file might be too large or the server is busy.';
            } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMsg = xhr.responseJSON.data.message;
            }
            
            $status.text(errorMsg).addClass('error');
            $button.prop('disabled', false).text('Optimize');
        }
    });
});
        
        // Bulk optimization
        if ($('#scanpro-bulk-optimize').length) {
            $('#scanpro-bulk-optimize').on('click', function() {
                var $button = $(this);
                var $progress = $('#scanpro-bulk-progress');
                var $progressBar = $progress.find('.scanpro-progress-bar');
                var $progressCurrent = $('#scanpro-progress-current');
                var $progressPercent = $('#scanpro-progress-percent');
                var totalImages = parseInt($('#scanpro-progress-total').text());
                var processedImages = 0;
                
                // Disable button and show progress
                $button.prop('disabled', true);
                $progress.show();
                
                // Get all image IDs
                var imageIds = [];
                $('#scanpro-unoptimized-images tbody tr').each(function() {
                    imageIds.push($(this).data('id'));
                });
                
                // Process images one by one
                function processNextImage() {
                    if (processedImages >= imageIds.length) {
                        // All images processed
                        $button.text('Optimization Complete!');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                        return;
                    }
                    
                    var attachmentId = imageIds[processedImages];
                    var $row = $('tr[data-id="' + attachmentId + '"]');
                    var $status = $row.find('.scanpro-optimization-result');
                    
                    $status.text('Optimizing...').removeClass('error success');
                    
                    $.ajax({
                        url: scanpro_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'scanpro_compress_image',
                            nonce: scanpro_params.nonce,
                            attachment_id: attachmentId
                        },
                        success: function(response) {
                            processedImages++;
                            
                            if (response.success) {
                                $status
                                    .addClass('success')
                                    .html('Saved ' + response.data.saved + ' (' + response.data.savings_percentage + '%)');
                                $row.find('.scanpro-optimize-single').prop('disabled', true).text('Optimized');
                            } else {
                                $status
                                    .addClass('error')
                                    .text(response.data.message);
                            }
                            
                            // Update progress
                            var percent = Math.round((processedImages / totalImages) * 100);
                            $progressBar.css('width', percent + '%');
                            $progressCurrent.text(processedImages);
                            $progressPercent.text(percent + '%');
                            
                            // Process next image
                            processNextImage();
                        },
                        error: function() {
                            processedImages++;
                            
                            $status
                                .addClass('error')
                                .text('Connection error');
                            
                            // Update progress
                            var percent = Math.round((processedImages / totalImages) * 100);
                            $progressBar.css('width', percent + '%');
                            $progressCurrent.text(processedImages);
                            $progressPercent.text(percent + '%');
                            
                            // Process next image
                            processNextImage();
                        }
                    });
                }
                
                // Start processing
                processNextImage();
            });
            
            // Single image optimization in bulk page
            $('.scanpro-optimize-single').on('click', function() {
                var $button = $(this);
                var attachmentId = $button.data('id');
                var $status = $button.siblings('.scanpro-optimization-result');
                
                // Disable button during optimization
                $button.prop('disabled', true).text('Optimizing...');
                
                $.ajax({
                    url: scanpro_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'scanpro_compress_image',
                        nonce: scanpro_params.nonce,
                        attachment_id: attachmentId
                    },
                    success: function(response) {
                        if (response.success) {
                            $status
                                .addClass('success')
                                .html('Saved ' + response.data.saved + ' (' + response.data.savings_percentage + '%)');
                            $button.text('Optimized');
                        } else {
                            $status
                                .addClass('error')
                                .text(response.data.message);
                            $button.prop('disabled', false).text('Optimize');
                        }
                    },
                    error: function() {
                        $status
                            .addClass('error')
                            .text('Connection error. Please try again.');
                        $button.prop('disabled', false).text('Optimize');
                    }
                });
            });
        }
    });

})(jQuery);