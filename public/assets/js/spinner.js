document.addEventListener('DOMContentLoaded', () => {
    const spinner = document.getElementById('global-spinner');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const toastContainer = document.getElementById('toast-container');

    const showSpinner = () => { if (spinner) spinner.style.display = 'block'; };
    const hideSpinner = () => { if (spinner) spinner.style.display = 'none'; };

    const showToast = (message, type = 'success') => {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type}`;
        toast.style.marginBottom = '10px';
        toast.innerText = message;

        toastContainer.appendChild(toast);

        // Auto remove after 3s
        setTimeout(() => {
            toast.remove();
        }, 3000);
    };

    const handleResponse = async (response, target) => {
        const text = await response.text();

        if (!response.ok) {
            showToast(`Error ${response.status}: ${response.statusText}`, 'danger');
            throw new Error(`Error ${response.status}: ${response.statusText}`);
        }

        // Try JSON first
        try {
            const data = JSON.parse(text);
            if (data.message) {
                showToast(data.message, 'success');
            }
            return data;
        } catch {
            // If HTML, inject into target
            if (target) {
                const el = document.querySelector(target);
                if (el) el.innerHTML = text;
            }
            return text;
        }
    };

    // Handle all forms
    document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            showSpinner();

            const target = form.getAttribute('data-target');
            const url = form.getAttribute('action');
            const method = form.getAttribute('method') || 'POST';

            const formData = new FormData(form);
            const jsonData = Object.fromEntries(formData.entries());

            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(jsonData)
                });
                await handleResponse(response, target);
            } catch (err) {
                console.error(err);
                showToast('An unexpected error occurred.', 'danger');
            } finally {
                hideSpinner();
            }
        });
    });

    // Handle all links
    document.querySelectorAll('a[data-ajax="true"]').forEach(link => {
        link.addEventListener('click', async e => {
            e.preventDefault();
            showSpinner();

            const target = link.getAttribute('data-target');
            const url = link.getAttribute('href');

            try {
                const response = await fetch(url, {
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                });
                await handleResponse(response, target);
            } catch (err) {
                console.error(err);
                showToast('An unexpected error occurred.', 'danger');
            } finally {
                hideSpinner();
            }
        });
    });
});


/* // autoAjax.js
document.addEventListener('DOMContentLoaded', () => {
    const spinner = document.getElementById('global-spinner');

    const showSpinner = () => { if (spinner) spinner.style.display = 'block'; };
    const hideSpinner = () => { if (spinner) spinner.style.display = 'none'; };

    const handleResponse = async (response, target) => {
        const text = await response.text();
        if (!response.ok) throw new Error(`Error ${response.status}: ${response.statusText}`);
        if (target) {
            const el = document.querySelector(target);
            if (el) el.innerHTML = text;
        }
        return text;
    };

    // Handle all forms with data-ajax="true"
    document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            showSpinner();

            const target = form.getAttribute('data-target');
            const url = form.getAttribute('action');
            const method = form.getAttribute('method') || 'POST';

            const formData = new FormData(form);
            const jsonData = Object.fromEntries(formData.entries());

            try {
                const response = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(jsonData)
                });
                await handleResponse(response, target);
            } catch (err) {
                console.error(err);
                alert('An error occurred. Check console for details.');
            } finally {
                hideSpinner();
            }
        });
    });

    // Handle all links with data-ajax="true"
    document.querySelectorAll('a[data-ajax="true"]').forEach(link => {
        link.addEventListener('click', async e => {
            e.preventDefault();
            showSpinner();

            const target = link.getAttribute('data-target');
            const url = link.getAttribute('href');

            try {
                const response = await fetch(url);
                await handleResponse(response, target);
            } catch (err) {
                console.error(err);
                alert('An error occurred. Check console for details.');
            } finally {
                hideSpinner();
            }
        });
    });
}); */



/* // globalAjax.js
const GlobalAjax = {
    spinner: document.getElementById('global-spinner'),

    showSpinner() {
        if (this.spinner) this.spinner.style.display = 'block';
    },

    hideSpinner() {
        if (this.spinner) this.spinner.style.display = 'none';
    },

    async request({ url, method = 'GET', data = null, target = null }) {
        this.showSpinner();

        try {
            const options = { method, headers: {} };

            if (data) {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }

            const response = await fetch(url, options);
            const text = await response.text(); // you can use .json() if API returns JSON

            if (!response.ok) throw new Error(`Error ${response.status}: ${response.statusText}`);

            // Inject HTML if target element is provided
            if (target) {
                const el = document.querySelector(target);
                if (el) el.innerHTML = text;
            }

            return text;

        } catch (err) {
            console.error(err);
            alert('An error occurred. Check console for details.');
        } finally {
            this.hideSpinner();
        }
    }
};
 */