let map;

window.addEventListener('load', () => {
    // Mapa je len na hlavnej stránke — inde nie je čo inicializovať (a Google Maps sa tam nenačítava).
    if (!document.getElementById('map')) {
        return;
    }
    if (typeof google !== 'undefined' && google.maps) {
        initMap();
    } else {
        console.error("Google Maps API sa nenačítalo.");
    }
});

/** Text od používateľov (názov, popis) do HTML okna na mape — inak by sa z neho stali značky a skripty. */
function escapeHtml(text) {
    return String(text ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

const markers = [];
let activeInfoWindow = null;
/** id hríbika → { marker, infowindow, position } — na otvorenie hríbika z odkazu /?hribik=ID */
const markersById = {};

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

            let imagesHtml = '';
            if (hrib.fotky && hrib.fotky.length > 0) {
                imagesHtml = `<div class="iw-photo-wrap"><div class="slideshow-container" id="slideshow-${hrib.id}">`;
                hrib.fotky.forEach((foto, index) => {
                    imagesHtml += `<div class="slide" style="display:${index===0?'block':'none'};"><img src="${escapeHtml(foto)}" class="iw-photo"></div>`;
                });
                if (hrib.fotky.length > 1) {
                    imagesHtml += `<a class="prev" data-id="${hrib.id}">&#10094;</a><a class="next" data-id="${hrib.id}">&#10095;</a>`;
                    imagesHtml += `<span class="iw-photo-count"><span class="iw-photo-current">1</span> / ${hrib.fotky.length}</span>`;
                }
                imagesHtml += `</div></div>`;
            }

            const rawDesc = hrib.description || '';
            const shortDesc = rawDesc.length > 120 ? rawDesc.substring(0, 120) + '\u2026' : rawDesc;

            let content = `<div class="infowindow">
                ${imagesHtml}
                <div class="iw-body">
                    <h6 class="iw-title"><a href="/${Number(hrib.id)}">${escapeHtml(hrib.title)}</a></h6>
                    ${shortDesc ? `<p class="iw-desc">${escapeHtml(shortDesc)}</p>` : ''}
                    <a href="/${Number(hrib.id)}" class="iw-btn">Zobraziť detail &rarr;</a>
                </div>
            </div>`;

            const infowindow = new google.maps.InfoWindow({ content });
            markersById[hrib.id] = { marker, infowindow, position: { lat: hrib.latitude, lng: hrib.longitude } };
            marker.addListener("click", function() {
                if (activeInfoWindow) activeInfoWindow.close();
                infowindow.open({ anchor: marker, map });
                activeInfoWindow = infowindow;
            });
        });



        // Stránky pohorí a krajov (data-fit-bounds): priblížiť mapu na hríbiky oblasti.
        if (mapEl.dataset.fitBounds && hribiky.length > 0) {
            const bounds = new google.maps.LatLngBounds();
            hribiky.forEach(h => bounds.extend({ lat: h.latitude, lng: h.longitude }));
            map.fitBounds(bounds, 40);
            google.maps.event.addListenerOnce(map, 'idle', () => {
                if (map.getZoom() > 14) map.setZoom(14); // jeden hríbik — nepribližovať až na dvor
            });
        }

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

    focusMushroomFromUrl();

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

    $("#addForm").on("submit", async function(e) {
        e.preventDefault();
        const submitButton = $('#rozcestnikFormSubmitButton');
        const originalText = disableButton( submitButton );
        const form = $('#addForm')[0];
        // Fotky sa pred odoslaním zmenšia v prehliadači (public/js/photo-resize.js).
        const formData = await buildUploadFormData(form);
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
                alert(formErrorMessage(xhr));
                enableButton( submitButton, originalText );
            }
        });
    });

    $("#addRozcestnikUpdateForm").on("submit", async function(e) {
        e.preventDefault();
        const submitButton = $('#rozcestnikUpdateFormSubmitButton');
        const originalText = disableButton( submitButton );
        const form = $('#addRozcestnikUpdateForm')[0];
        const formData = await buildUploadFormData(form);

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
                // Formulár nemažeme — používateľ by prišiel o napísaný text.
                alert(formErrorMessage(xhr));
                enableButton( submitButton, originalText);
            }
        });
    });
}

/**
 * Odkaz „Zobraziť na mape“ z detailu hríbika vedie na /?hribik=ID — mapu vycentrujeme
 * a priblížime na ten hríbik a otvoríme jeho okno (aj keď je inak v zhluku).
 */
function focusMushroomFromUrl() {
    const id = new URLSearchParams(window.location.search).get('hribik');
    const item = id && markersById[id];
    if (!item) {
        return;
    }

    map.setCenter(item.position);
    map.setZoom(16);

    // Zhluky (MarkerClusterer) sa prepočítajú až po zmene zoomu — okno otvoríme, keď je mapa v pokoji.
    google.maps.event.addListenerOnce(map, 'idle', function () {
        setTimeout(function () {
            if (activeInfoWindow) activeInfoWindow.close();
            if (item.marker.map) {
                item.infowindow.open({ anchor: item.marker, map });
            } else {
                // Hríbik je stále v zhluku (iný hríbik takmer na tom istom mieste) — okno ukážeme na jeho súradniciach.
                item.infowindow.setPosition(item.position);
                item.infowindow.open({ map });
            }
            activeInfoWindow = item.infowindow;
        }, 300);
    });
}

function showSlide(slideshowId, n) {
    const slides = $(`#${slideshowId} .slide`);
    if (n >= slides.length) n = 0;
    if (n < 0) n = slides.length - 1;

    slides.hide();
    $(slides[n]).show();
    $(`#${slideshowId}`).data('current', n);
    $(`#${slideshowId}`).closest('.iw-photo-wrap').find('.iw-photo-current').text(n + 1);
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

/** FormData so zmenšenými fotkami; ak photo-resize.js nie je k dispozícii, bežné FormData. */
async function buildUploadFormData(form) {
    if (window.HribikyPhotos) {
        try {
            return await window.HribikyPhotos.buildFormData(form);
        } catch (e) {
            // zmenšenie nesmie zablokovať odoslanie
        }
    }
    return new FormData(form);
}

/** Zrozumiteľná chybová správa z odpovede servera (errors môže byť pole aj text). */
function formErrorMessage(xhr) {
    let msg = 'Chyba pri odoslaní formulára.';
    const data = xhr.responseJSON;
    if (data && data.errors) {
        msg += '\n' + (Array.isArray(data.errors) ? data.errors.join('\n') : data.errors);
    } else if (data && data.error) {
        msg += '\n' + data.error;
    } else if (xhr.status === 0) {
        msg += '\nSkontroluj pripojenie na internet a skús to znova.';
    }
    return msg;
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
