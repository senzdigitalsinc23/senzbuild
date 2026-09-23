$("#momoForm").submit(function (e) {
    e.preventDefault();

    $.ajax({
        url: "/api/momo/pay",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({
            phone: $("#phone").val(),
            amount: $("#amount").val()
        }),
        success: function (data) {
            if (data.success) {
                $("#paymentResult").html(
                    `<div class="alert alert-success">${data.message} - Transaction ID: ${data.data.transactionId}</div>`
                );
            } else {
                $("#paymentResult").html(
                    `<div class="alert alert-danger">Error: ${data.message}</div>`
                );
            }
        },
        error: function (xhr) {
            $("#paymentResult").html(
                `<div class="alert alert-danger">Server Error: ${xhr.responseText}</div>`
            );
        }
    });
});
