// Payment method page: the core shows a payment module's settings on select[name=module] change;
// cash on delivery belongs to "Manual" payments only (the server stores 0 for any module anyway).
$(document).on('change', 'select[name=module]', function () {
    var manual = $(this).val() === 'null' || $(this).val() === '';
    $('.np-codpay').toggleClass('hidden', !manual);
});
$(document).on('change', '.np-codpay__switch', function () {
    $(this).closest('.np-codpay').find('.np-codpay__kinds').toggleClass('hidden', !this.checked);
});
