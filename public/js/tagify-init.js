document.addEventListener('DOMContentLoaded', function () {
    const input = document.querySelector('input[id$="_tagsText"]');
    if (!input) return;

    const tagify = new Tagify(input, {
        delimiters: ',',
        trim: true,
        duplicates: false,
        // Zapíše späť do inputu čiarkami oddelené hodnoty (nie JSON)
        originalInputValueFormat: function (values) {
            return values.map(function (v) { return v.value; }).join(', ');
        }
    });

    // Tagify spravuje input interne a pri submite ho nemusí mať aktuálny.
    // Pred odoslaním formulára ho explicitne naplníme aktuálnymi tagmi.
    const form = input.closest('form');
    if (form) {
        form.addEventListener('submit', function () {
            input.value = tagify.value.map(function (v) { return v.value; }).join(', ');
        });
    }
});