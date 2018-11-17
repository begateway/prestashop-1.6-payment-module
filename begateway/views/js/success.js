$(function () {
    var $confirmation = $('#begateway__confirmation');
    var url = $confirmation.data('update-url'),
        order_id = $confirmation.data('order-id'),
        wait = 5000;

    updateStatus();

    function updateStatus() {
        $.ajax({
            url: url,
            dataType: 'json',
            data: {
                order_id: order_id
            },
            success: function (data) {
                if (data.success) {
                    showSuccess(data.message);
                }
                else if (data.retry) {
                    setTimeout(updateStatus, wait);
                }
                else {
                    showWarning(data.message);
                }
            },
            error: function (xhr, textStatus) {
                showMessage('error', textStatus);
            }
        });
    }

    function hideLoading() {
        $('#begateway__loading').hide();
    }

    function showSuccess(message) {
        hideLoading();
        showMessage(message, 'alert-success');
    }

    function showWarning(message) {
        hideLoading();
        showMessage(message, 'alert-warning');
    }

    function showMessage(message, cls) {
        hideLoading();
        var $message = $('#begateway__message');
        $message.addClass(cls);
        if (message) {
            $message.text(message);
        }
        $message.show();
    }

});
