document.addEventListener("DOMContentLoaded", function () {
    const momoForm = document.getElementById("momoForm");
    const resultBox = document.getElementById("paymentResult");

    momoForm.addEventListener("submit", async function (e) {
        e.preventDefault();

        const phone = document.getElementById("phone").value;
        const amount = document.getElementById("amount").value;

        try {
            const response = await fetch("/api/momo/pay", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({ phone, amount })
            });

            const data = await response.json();

            if (data.success) {
                resultBox.innerHTML = `<div class="alert alert-success">${data.message} - Transaction ID: ${data.data.transactionId}</div>`;
            } else {
                resultBox.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
            }
        } catch (err) {
            resultBox.innerHTML = `<div class="alert alert-danger">Request failed: ${err.message}</div>`;
        }
    });
});
