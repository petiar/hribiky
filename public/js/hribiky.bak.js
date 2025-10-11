$(function() {
    const map = L.map('map').setView([48.7, 19.7], 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const mushroomIcon = L.icon({
        iconUrl: '/images/mushroom.png',
        iconSize: [32, 37],
        iconAnchor: [16, 37]
    });

    // Načítanie markerov
    $.getJSON('/', function(data) {
        data.rozcestniky.forEach(r => {
            L.marker([r.latitude, r.longitude], { icon: mushroomIcon })
                .addTo(map)
                .bindPopup(`<b>${r.name}</b><br><a href="/rozcestnik/${r.id}">Detail</a>`);
        });
    });

    $('#addRozcestnik').click(function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                $('#latitude').val(pos.coords.latitude);
                $('#longitude').val(pos.coords.longitude);
                $('#altitude').val(pos.coords.altitude || '');
                $('#addModal').modal('show');
            }, err => alert('Nepodarilo sa získať GPS.'));
        } else {
            alert('Geolokácia nie je podporovaná.');
        }
    });

    $('#addForm').on('submit', function(e) {
        e.preventDefault();
        const form = $('#addForm')[0];
        const formData = new FormData(form);

        // pridanie GPS súradníc
        formData.append('latitude', $('#latitude').val());
        formData.append('longitude', $('#longitude').val());

        $.ajax({
            url: '/rozcestnik/create',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: () => {
                alert('Ďakujeme! Hríbik bol odoslaný na overenie.');
                $('#addModal').modal('hide');
            },
            error: () => alert('Chyba pri odoslaní formulára.')
        });
    });
});
