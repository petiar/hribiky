document.addEventListener('DOMContentLoaded', function () {
    const input = document.querySelector('input[id$="_tagsText"]');
    if (!input) return;

    new Tagify(input, {
        delimiters: ',',
        trim: true,
        duplicates: false,
        // Zapíše späť do inputu čiarkami oddelené hodnoty (nie JSON)
        originalInputValueFormat: function (values) {
            return values.map(function (v) { return v.value; }).join(', ');
        }
    });
});