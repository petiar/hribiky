let map;

function initMap() {
    const center = { lat: 48.669, lng: 19.699 };
    map = new google.maps.Map(document.getElementById("map"), {
        zoom: 7,
        center: center
    });

    if (false && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const userPos = {
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude
                };
                map.setCenter(userPos);

                new google.maps.Marker({
                    position: userPos,
                    map: map,
                    title: "Vaša poloha"
                });
            },
            function() {
                console.log("Geolokácia zakázaná alebo neúspešná.");
            }
        );
    }

    if (typeof hribiky !== 'undefined') {
        $.each(hribiky, function(i, hrib) {
            const marker = new google.maps.Marker({
                position: { lat: hrib.latitude, lng: hrib.longitude },
                map: map,
                title: hrib.title,
                icon: {
                    url: "/images/mushroom.png", // cesta k obrázku
                    scaledSize: new google.maps.Size(40, 40), // veľkosť ikonky (šírka, výška)
                    origin: new google.maps.Point(0, 0),      // pozícia v rámci obrázka
                    anchor: new google.maps.Point(20, 40),    // bod, ktorý sa „ukotví“ na mape
                },
            });

            let imagesHtml = `<div class="slideshow-container" id="slideshow-${hrib.id}">`;
            hrib.fotky.forEach((foto, index) => {
                imagesHtml += `
                <div class="slide" style="display:${index===0?'block':'none'};">
                    <img src="${foto}" style="width:200px; height:auto;">
                </div>
            `;
            });

            if (hrib.fotky.length > 1) {
                imagesHtml += `
            <a class="prev" data-id="${hrib.id}">&#10094;</a>
            <a class="next" data-id="${hrib.id}">&#10095;</a>
            `
            }
            imagesHtml += `</div>`;

            let content = `<div class="infowindow p-2 mt-2">
                <h5><a href="/${hrib.id}">${hrib.title}</a></h5>
                <p>${hrib.description || ''}</p>`;
            if (hrib.fotky) {
                content += imagesHtml;
            }
            content += `</div>`;

            const infowindow = new google.maps.InfoWindow({ content });
            marker.addListener("click", function() {
                infowindow.open(map, marker);
            });
        });
    }

    $('#addRozcestnik').click(function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(async pos => {
                $('#rozcestnik_latitude').val(pos.coords.latitude);
                $('#rozcestnik_longitude').val(pos.coords.longitude);

                const alt = pos.coords.altitude;
                if ( alt ) {
                    $('#altitudeText').show();
                    $('#altitudeGuess').html( alt.toFixed() );
                }
                else {
                    $('#altitudeText').hide();
                }

                const response = await fetch(`/api/nearby?lat=${pos.coords.latitude}&lng=${pos.coords.longitude}&radius=100`);

                const data = await response.json();

                if (data.hribiky && data.hribiky.length > 0) {
                    let html = `<h5>V tvojom okolí sa už nachádzajú tieto hríbiky:</h5><ul class="list-group mb-3">`;
                    data.hribiky.forEach(h => {
                        html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold">${h.title}, (${h.altitude} mnm)</span>
                                    <button class="btn btn-sm btn-primary update-hribik" data-id="${h.id}">
                                Aktualizovať
                            </button>
                         </li>`;
                    });
                    html += `</ul><hr><button id="newHribikAnyway" class="btn btn-success">Chcem pridať nový, v zozname som ho nenašiel</button>`;

                    document.getElementById('existing-modal-body').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('existingHribikModal')).show();
                }
                else {
                    $('#addModal').modal('show');
                }

            }, err => alert('Nepodarilo sa získať GPS.'));
        } else {
            alert('Geolokácia nie je podporovaná.');
        }
    });

    $("#addForm").on("submit", function(e) {
        e.preventDefault();
        const submitButton = $('#rozcestnikFormSubmitButton');
        const originalText = disableButton( submitButton );
        $('#rozcestnik__token').val(document.querySelector('meta[name="rozcestnik_item_csrf_token"]').content);
        const form = $('#addForm')[0];
        const formData = new FormData(form);

        formData.append('rozcestnik__token', document.querySelector('meta[name="rozcestnik_item_csrf_token"]').content);
        $.ajax({
            url: '/rozcestnik/create',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                alert('Hríbik bol pridaný! Akonáhle ho overíme, ocitne sa na mape. Vďaka!');
                $('#addModal').modal('hide');
                form.reset();
                enableButton( submitButton, originalText );
            },
            error: function(xhr) {
                let msg = 'Chyba pri odoslaní formulára.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg += '\n' + xhr.responseJSON.errors;
                }
                alert(msg);
                enableButton( submitButton, originalText );
            }
        });
    });

    $("#addRozcestnikUpdateForm").on("submit", function(e) {
        e.preventDefault();
        const submitButton = $('#rozcestnikUpdateFormSubmitButton');
        const originalText = disableButton( submitButton );

        $('#rozcestnik_update__token').val(document.querySelector('meta[name="rozcestnik_update_item_csrf_token"]').content);
        const form = $('#addRozcestnikUpdateForm')[0];
        const formData = new FormData(form);

        formData.append('rozcestnik_update__token', document.querySelector('meta[name="rozcestnik_update_item_csrf_token"]').content);
        $.ajax({
            url: '/rozcestnik-update',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                alert('Komentár k bríbiku bol pridaný, ďakujeme. Akonáhle ho overíme, ocitne sa pri hríbiku.');
                $('#addRozcestnikUpdate').modal('hide');
                form.reset();
                enableButton( submitButton, originalText);
            },
            error: function(xhr) {
                let msg = 'Chyba pri odoslaní formulára.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg += '\n' + xhr.responseJSON.errors;
                }
                alert(msg);
                form.reset();
                enableButton( submitButton, originalText);
            }
        });
    });
}

function showSlide(slideshowId, n) {
    const slides = $(`#${slideshowId} .slide`);
    if (n >= slides.length) n = 0;
    if (n < 0) n = slides.length - 1;

    slides.hide();
    $(slides[n]).show();
    $(`#${slideshowId}`).data('current', n);
}

$(document).on('click', '.prev, .next', function() {
    const slideshowId = 'slideshow-' + $(this).data('id');
    let current = $(`#${slideshowId}`).data('current') || 0;

    if ($(this).hasClass('next')) {
        current++;
    } else {
        current--;
    }

    showSlide(slideshowId, current);
});

function openAddForm(lat, lng) {
    document.querySelector('#addModal input[id="mushroom_latitude"]').value = lat;
    document.querySelector('#addModal input[id="mushroom_longitude"]').value = lng;

    const modalB = new bootstrap.Modal(document.getElementById('addModal'));
    modalB.show();
}

function disableButton(el) {
    const originalText = el.html();
    el.prop('disabled', true);
    el.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Odosielam...');
    return originalText;
}

function enableButton(el, originalText) {
    el.prop('disabled', false);
    el.html( originalText );
}

document.addEventListener('click', e => {
    if (e.target.classList.contains('update-hribik')) {
        const existingModal = bootstrap.Modal.getInstance(document.getElementById('existingHribikModal'));
        existingModal.hide();
        const id = e.target.dataset.id;
        document.querySelector('#addRozcestnikUpdate input[name="mushroom_comment[rozcestnik_id]"]').value = id;

        const rozcestnikUpdateModal = new bootstrap.Modal(document.getElementById('addRozcestnikUpdate'));
        rozcestnikUpdateModal.show();
    }

    if (e.target.id === 'newHribikAnyway') {
        const existingModal = bootstrap.Modal.getInstance(document.getElementById('existingHribikModal'));
        existingModal.hide();
        navigator.geolocation.getCurrentPosition(pos => {
            openAddForm(pos.coords.latitude, pos.coords.longitude);
        });
    }

    if (e.target.classList.contains('update-foto')) {
        const src = e.target.getAttribute('data-bs-src');
        document.getElementById('lightboxImage').setAttribute('src', src);
    }

    if (e.target.id === 'lightboxImage') {
        const lightboxModal = bootstrap.Modal.getInstance(document.getElementById('lightboxModal'));
        lightboxModal.hide();
    }
});

