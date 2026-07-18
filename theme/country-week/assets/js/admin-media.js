/**
 * Wires up the native wp.media picker for the Country edit screen's
 * flag/map image fields and photo gallery. No external media-library
 * or uploader dependency — this is entirely WordPress core's own JS API.
 */
(function ($) {
    'use strict';

    $(function () {
        $('.country-week-media-field').each(function () {
            var $field = $(this);
            var $input = $field.find('input[type="hidden"]');
            var $preview = $field.find('.country-week-media-field__preview');
            var $select = $field.find('.country-week-media-select');
            var $remove = $field.find('.country-week-media-remove');
            var frame;

            $select.on('click', function (event) {
                event.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: $select.text(),
                    multiple: false,
                    library: { type: 'image' },
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.id);
                    $preview.html('<img src="' + attachment.url + '" alt="" style="max-width:100px;height:auto;display:block;">');
                    $remove.show();
                });

                frame.open();
            });

            $remove.on('click', function (event) {
                event.preventDefault();
                $input.val('');
                $preview.empty();
                $remove.hide();
            });
        });

        var $gallery = $('.country-week-gallery-field');
        var $galleryList = $gallery.find('.country-week-gallery-field__list');
        var galleryFrame;

        $gallery.find('.country-week-gallery-add').on('click', function (event) {
            event.preventDefault();

            if (!galleryFrame) {
                galleryFrame = wp.media({
                    title: countryWeekAdminMedia.galleryTitle,
                    multiple: true,
                    library: { type: 'image' },
                });

                galleryFrame.on('select', function () {
                    var selection = galleryFrame.state().get('selection');

                    selection.each(function (attachment) {
                        var data = attachment.toJSON();
                        var $item = $('<li></li>');
                        $item.append(
                            $('<img>', {
                                src: (data.sizes && data.sizes.thumbnail) ? data.sizes.thumbnail.url : data.url,
                                css: { width: '60px', height: '60px', objectFit: 'cover' },
                            })
                        );
                        $item.append(
                            $('<input>', { type: 'hidden', name: 'gallery_image_id[]', value: data.id })
                        );
                        $galleryList.append($item);
                    });
                });
            }

            galleryFrame.open();
        });
    });
})(jQuery);
