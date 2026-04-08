(function ($) {
    'use strict';

    $(function () {
        var $button = $('#legaciti-manual-sync');
        var $status = $('#legaciti-sync-status');
        var $result = $('#legaciti-sync-result');
        var $log = $('#legaciti-sync-log');

        $button.on('click', function () {
            $button.prop('disabled', true);
            $status.text(legacitiAdmin.syncingText || 'Syncing...');
            $result.hide();

            $.post(legacitiAdmin.ajaxUrl, {
                action: 'legaciti_manual_sync',
                nonce: legacitiAdmin.nonce,
            }).done(function (response) {
                if (response.success) {
                    $status.text('Sync complete!');
                    $log.text(JSON.stringify(response.data, null, 2));
                } else {
                    $status.text('Sync failed.');
                    $log.text(response.data?.message || 'Unknown error');
                }
                $result.show();
            }).fail(function () {
                $status.text('Request failed.');
            }).always(function () {
                $button.prop('disabled', false);
            });
        });
    });
})(jQuery);
