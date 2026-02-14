document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('input-logo');
    if (!input) return;

    input.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (event) {
            const preview = document.getElementById('preview-logo');
            const placeholder = document.getElementById('preview-placeholder');

            preview.src = event.target.result;
            preview.style.display = 'block';

            if (placeholder) {
                placeholder.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);
    });
});