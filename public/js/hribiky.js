let map;

window.addEventListener('load', () => {
    if (typeof google !== 'undefined' && google.maps) {
        initMap();
    } else {
        console.error("Google Maps API sa nenačítalo.");
    }
});

const markers = [];

function initMap() {
    const mapEl = document.getElementById("map");
    if (!mapEl) {
        // Na tejto stránke nie je mapa
        return;
    }

    const bodyEl = document.body;
    const locale = (bodyEl.dataset.locale);

    let center = { lat: 48.669, lng: 19.699 }; // Slovensko
    if (locale === 'cs') {
        center = { lat: 49.8175, lng: 15.4730 }; // Česko
    }

    map = new google.maps.Map(mapEl, {
        zoom: 8,
        center: center,
        mapId: '2e47f3a28a3051b243c5e291'
    });

    if (typeof hribiky !== 'undefined') {
        $.each(hribiky, function(i, hrib) {
            const markerContent = document.createElement("div");
            const markerImg = document.createElement("img");
            markerImg.src = "/images/mushroom.png";
            markerImg.alt = hrib.title || "Hríbik";
            markerImg.style.width = "40px";
            markerImg.style.height = "40px";
            markerContent.appendChild(markerImg);

            const marker = new google.maps.marker.AdvancedMarkerElement({
                position: { lat: hrib.latitude, lng: hrib.longitude },
                map: map,
                title: hrib.title,
                content: markerContent
            });

            markers.push(marker);

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
                infowindow.open({ anchor: marker, map });
            });
        });



        const clusterer = new markerClusterer.MarkerClusterer({
            map,
            markers,
            renderer: {
                render: ({ count, position }) => {
                    // obal pre ikonku + biely kruh
                    const wrapper = document.createElement("div");
                    wrapper.className = "cluster-wrapper";

                    // ikonka hríba (tvoje PNG)
                    const img = document.createElement("img");
                    img.src = "/images/mushroom.png";
                    img.alt = "Cluster";
                    img.className = "cluster-icon";
                    wrapper.appendChild(img);

                    // biely kruh s číslom
                    const bubble = document.createElement("div");
                    bubble.className = "cluster-bubble";
                    bubble.textContent = String(count);
                    wrapper.appendChild(bubble);

                    return new google.maps.marker.AdvancedMarkerElement({
                        map,
                        position,
                        content: wrapper,
                        zIndex: count, // ak chceš, nech väčšie clustre sú „vyššie“
                    });
                },
            },
        });
    }

    $('#addRozcestnik').click(function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(async pos => {
                $('#mushroom_latitude').val(pos.coords.latitude);
                $('#mushroom_longitude').val(pos.coords.longitude);

                getCountryFromLatLng(pos.coords.latitude, pos.coords.longitude);

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
                    const list = document.getElementById('existingMushroomsList');
                    const templateLi = list.querySelector('li');
                    list.innerHTML = '';

                    data.hribiky.forEach(h => {
                        const newLi = templateLi.cloneNode(true);
                        const span = newLi.querySelector('.mushroomTitle');
                        span.textContent = h.title + ", (" + h.altitude + ") m. n. m";
                        const button = newLi.querySelector('button.update-hribik');
                        button.dataset.id = h.id;
                        list.appendChild(newLi);
                    });
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
        const form = $('#addForm')[0];
        const formData = new FormData(form);
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
        const form = $('#addRozcestnikUpdateForm')[0];
        const formData = new FormData(form);

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

/** @return string */
function getCountryFromLatLng(lat, lng) {
    const geocoder = new google.maps.Geocoder();
    const latlng = { lat: parseFloat(lat), lng: parseFloat(lng) };

    geocoder.geocode({ location: latlng }, (results, status) => {
        if (status === "OK") {
            if (results[0]) {
                let country = null;
                for (const component of results[0].address_components) {
                    if (component.types.includes("country")) {
                        country = component.short_name; // CZ alebo SK
                        break;
                    }
                }
                $('#mushroom_country').val(country);
            } else {
                $('#mushroom_country').val('XX');
            }
        }
    });
}
