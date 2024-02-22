import {Modal} from 'bootstrap';

window.addEventListener('load', (event) => {
    const mediaTypeModal = document.getElementById('mediaTypeModal')

    if (!mediaTypeModal) {
        return;
    }

    let modalSearchUrl = '';

    /**
     * Open modal
     */
    mediaTypeModal.addEventListener('show.bs.modal', function (event) {
        // Button that triggered the modal
        mediaTypeModal.clickedButton = event.relatedTarget
        const mediaInput = document.getElementById(mediaTypeModal.clickedButton.dataset.mediaTypeField);

        const mediaTypeModalBody = mediaTypeModal.querySelector('.modal-body');
        const mediaTypeModalFooter = mediaTypeModal.querySelector('.modal-footer');
        mediaTypeModal.querySelector('[data-media-type-select]').classList.add('disabled');

        mediaTypeModalFooter.style.setProperty('display', 'none');

        mediaTypeModalBody.innerHTML = '<div class="p-5 text-center"><div class="spinner-border text-secondary" role="status">\n' +
            '  <span class="visually-hidden">Loading...</span>\n' +
            '</div></div>';

        // get valid types
        const mediaTypes = mediaInput.getAttribute('data-media-type-types')

        modalSearchUrl = mediaTypeModal.clickedButton.dataset.searchUrl; // + '?page=1&rpp=&order=&text=&' + mediaTypes.split(',').map((v) => 'valid_types%5B%5D=' + v).join('&');
        loadSearchPage(modalSearchUrl);
    });

    /**
     * Load modal page with media list
     */
    function loadSearchPage(url) {
        const mediaTypeModalBody = mediaTypeModal.querySelector('.modal-body');
        const mediaTypeModalFooter = mediaTypeModal.querySelector('.modal-footer');

        const http_request = new XMLHttpRequest();
        http_request.onreadystatechange = function () {
            if (http_request.readyState === 4) {
                mediaTypeModalBody.innerHTML = http_request.response;
                mediaTypeModalFooter.style.setProperty('display', '');

                mediaTypeModal.querySelector('[data-media-type-select]').classList.add('disabled');

                const searchForm = mediaTypeModalBody.querySelector('form');
                searchForm.onsubmit = function (event) {
                    event.preventDefault();

                    const formData = new FormData(event.target);
                    const data = [...formData.entries()];
                    const asString = data
                        .map(x => `${encodeURIComponent(x[0])}=${encodeURIComponent(x[1])}`)
                        .join('&');

                    loadSearchPage(searchForm.action + '?' + asString);
                }

                document.querySelectorAll('.modal-media .page-link').forEach(function (link) {
                    link.dataset.pageHref = link.href;
                    link.setAttribute('href', '#');
                });
            }
        };
        http_request.open('GET', url, true);
        http_request.send();
    }

    document.addEventListener('click', function (event) {
        if (!event.target.matches('[data-media-modal-create-href]')) {
            return;
        }

        const createFormUrl = event.target.dataset.mediaModalCreateHref;

        loadCreateForm(createFormUrl);
    });

    document.addEventListener('click', function (event) {
        if (!event.target.matches('.modal-media .page-link')) {
            return;
        }

        loadSearchPage(event.target.dataset.pageHref);

        event.preventDefault();

        return false;
    });

    function loadCreateForm(createFormUrl) {
        const mediaTypeModalBody = mediaTypeModal.querySelector('.modal-body');
        const mediaTypeModalFooter = mediaTypeModal.querySelector('.modal-footer');

        const http_request = new XMLHttpRequest();
        http_request.onreadystatechange = function () {
            if (http_request.readyState === 4) {
                mediaTypeModalBody.innerHTML = http_request.response;
                mediaTypeModalFooter.style.setProperty('display', '');
                configureCreateForm(createFormUrl)
            }
        };
        http_request.open('GET', createFormUrl, true);
        http_request.send();
    }

    function configureCreateForm(createFormUrl) {
        const mediaTypeModalBody = mediaTypeModal.querySelector('.modal-body');
        const mediaTypeModalFooter = mediaTypeModal.querySelector('.modal-footer');

        const createForm = mediaTypeModalBody.querySelector('form');
        createForm.onsubmit = function (event) {
            event.preventDefault();
            let formData = new FormData(createForm);

            [...createForm.querySelectorAll('input[type=file]')].forEach((inputFile) => formData.append(inputFile.attributes['name'], inputFile.files[0]));

            const xhr = new XMLHttpRequest()
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 201) {
                        loadSearchPage(modalSearchUrl);
                    } else {
                        mediaTypeModalBody.innerHTML = xhr.response;
                        configureCreateForm(createFormUrl);
                    }
                }
            }

            xhr.open('POST', createFormUrl);
            xhr.send(formData);
        }
    }


    /**
     * Click on modal media, to be selected
     */
    document.addEventListener('click', function (event) {
        let media = null;
        if (!event.target || !event.target.hasAttribute('data-media-type')) {
            for (let i = 0; i < event.composedPath().length; i++) {
                if (event.composedPath()[i] instanceof Element && event.composedPath()[i].matches('[data-media-type]')) {
                    media = event.composedPath()[i];
                    break;
                }
            }

            if (!media) {
                return;
            }
        } else {
            media = event.target;
        }

        mediaTypeModal.querySelectorAll('.sfs-media-selected').forEach(function (element) {
            element.classList.remove('sfs-media-selected');
        });

        media.classList.add('sfs-media-selected');

        mediaTypeModal.querySelector('[data-media-type-select]').classList.remove('disabled');
    });

    /**
     * Double click on modal media, to commit selection
     */
    document.addEventListener('dblclick', function (event) {
        let media = null;
        if (!event.target || !event.target.hasAttribute('data-media-type')) {
            for (let i = 0; i < event.composedPath().length; i++) {
                if (event.composedPath()[i] instanceof Element && event.composedPath()[i].matches('[data-media-type]')) {
                    media = event.composedPath()[i];
                    break;
                }
            }

            if (!media) {
                return;
            }
        } else {
            media = event.target;
        }

        selectMedia(media);
    });

    function selectMedia(selectedMedia) {
        const mediaInput = document.getElementById(mediaTypeModal.clickedButton.dataset.mediaTypeField);
        const mediaInputWidget = document.getElementById(mediaTypeModal.clickedButton.dataset.mediaTypeField + '_widget');
        const versionSelector = document.querySelector('[data-media-type-select-version][data-media-type-field=' + mediaInput.id + ']');
        let versionSelect;

        if (versionSelector) {
            versionSelect = versionSelector.nextElementSibling;
            versionSelect.innerHTML = '';
        }

        // clean previews data versions
        for (let key in mediaInput.dataset) {
            if (!key.match(/^(mediaImage|mediaVideo|mediaPicture|mediaVideoSet)/i)) {
                continue;
            }
            delete mediaInput.dataset[key];
        }

        // sets input hidden value and data elements
        mediaInput.value = selectedMedia.dataset.mediaId;
        mediaInput.dataset.mediaType = selectedMedia.dataset.mediaType;
        // propagate data version
        for (let key in selectedMedia.dataset) {
            if (!key.match(/^(mediaImage|mediaVideo|mediaPicture|mediaVideoSet)/i)) {
                continue;
            }
            mediaInput.dataset[key] = selectedMedia.dataset[key];

            // TODO show dropdown-headers

            if (versionSelector) {
                if (key.match(/^mediaImage/i)) {
                    let versionValue = key.replace(/^mediaImage\-?/i, '');
                    versionValue = versionValue.charAt(0).toLowerCase() + versionValue.slice(1);
                    versionSelect.insertAdjacentHTML('beforeend', '<li><a class="dropdown-item" data-media-version-value="image#' + versionValue + '" href="#">' + versionValue + '</a></li>');
                } else if (key.match(/^mediaVideoSet/i)) {
                    let versionValue = key.replace(/^mediaVideoSet\-?/i, '');
                    versionValue = versionValue.charAt(0).toLowerCase() + versionValue.slice(1);
                    versionSelect.insertAdjacentHTML('beforeend', '<li><a class="dropdown-item" data-media-version-value="videoSet#' + versionValue + '" href="#">' + versionValue + '</a></li>');
                } else if (key.match(/^mediaVideo/i)) {
                    let versionValue = key.replace(/^mediaVideo\-?/i, '');
                    versionValue = versionValue.charAt(0).toLowerCase() + versionValue.slice(1);
                    versionSelect.insertAdjacentHTML('beforeend', '<li><a class="dropdown-item" data-media-version-value="video#' + versionValue + '" href="#">' + versionValue + '</a></li>');
                } else if (key.match(/^mediaPicture/i)) {
                    let versionValue = key.replace(/^mediaPicture\-?/i, '');
                    versionValue = versionValue.charAt(0).toLowerCase() + versionValue.slice(1);
                    versionSelect.insertAdjacentHTML('beforeend', '<li><a class="dropdown-item" data-media-version-value="picture#' + versionValue + '" href="#">' + versionValue + '</a></li>');
                }
            }
        }

        if (versionSelector) {
            // select first (or default) version (li > a)
            let firstOption = versionSelect.children[0].children[0];
            firstOption.click();
        }

        // show media name
        document.getElementById(mediaTypeModal.clickedButton.dataset.mediaTypeField + '_text').innerHTML = selectedMedia.dataset.mediaName;

        // show thumbnail on widget
        const widget = document.getElementById(mediaTypeModal.clickedButton.dataset.mediaTypeWidget);
        const thumbnail = widget.querySelector('[data-media-type-thumbnail]');
        if (thumbnail) {
            if (mediaInput.dataset['mediaImage-_thumbnail']) {
                thumbnail.innerHTML = mediaInput.dataset['mediaImage-_thumbnail'];
            } else {
                thumbnail.innerHTML = '';
            }
        }

        // hides modal
        Modal.getInstance(mediaTypeModal).hide();

        mediaInputWidget.querySelector('[data-media-type-clean]').classList.remove('disabled');
        let selectVersion = mediaInputWidget.querySelector('[data-media-type-select-version]');
        selectVersion && selectVersion.classList.remove('disabled');

        // dispatches media selected event
        mediaInput.dispatchEvent(new Event('sfs_media.selected', {bubbles: true}));
    }

    /**
     * Click on modal Select button, to commit selection
     */
    document.addEventListener('click', function (event) {
        if (!event.target || !event.target.hasAttribute('data-media-type-select')) return;
        const selectedMedia = mediaTypeModal.querySelector('.sfs-media-selected');
        if (!selectedMedia) {
            return;
        }

        selectMedia(selectedMedia);
    });

    /**
     * Click on form clean button, to unselect media
     */
    document.addEventListener('click', function (event) {
        if (!event.target || !(event.target.hasAttribute('data-media-type-clean') || event.target.parentElement.hasAttribute('data-media-type-clean'))) return;

        let cleanButton;

        if (event.target.hasAttribute('data-media-type-clean')) {
            cleanButton = event.target;
        } else { // event.target.parentElement.hasAttribute('data-media-type-clean')
            cleanButton = event.target.parentElement;
        }

        const mediaInput = document.getElementById(cleanButton.dataset.mediaTypeField);
        const mediaInputWidget = document.getElementById(cleanButton.dataset.mediaTypeField + '_widget');
        const versionSelector = document.querySelector('[data-media-type-select-version][data-media-type-field=' + mediaInput.id + ']');

        mediaInput.value = '';
        document.getElementById(cleanButton.dataset.mediaTypeField + '_text').innerHTML = '';

        if (versionSelector) {
            versionSelector.innerHTML = '';
            versionSelector.nextElementSibling.innerHTML = '';
            const mediaVersionInput = document.getElementById(versionSelector.dataset.mediaVersionTypeField);
            mediaVersionInput.setAttribute('value', '');
            mediaVersionInput.value = '';
        }

        const widget = document.getElementById(cleanButton.dataset.mediaTypeWidget);

        const thumbnail = widget.querySelector('[data-media-type-thumbnail]');
        if (thumbnail) {
            thumbnail.innerHTML = '';
        }

        // propagate data version
        for (let key in mediaInput.dataset) {
            if (!key.match(/^(mediaImage|mediaVideo|mediaPicture|mediaVideoSet)/i)) {
                continue;
            }
            delete mediaInput.dataset[key]
        }

        mediaInputWidget.querySelector('[data-media-type-clean]').classList.add('disabled');
        let selectVersion = mediaInputWidget.querySelector('[data-media-type-select-version]');
        selectVersion && selectVersion.classList.add('disabled');

        // dispatches media unselected event
        mediaInput.dispatchEvent(new Event('sfs_media.unselected', {bubbles: true}));

    });

    /**
     * Click on form select version button
     */
    document.addEventListener('click', function (event) {
        if (!event.target || !event.target.hasAttribute('data-media-version-value')) return;
        const selectedOption = event.target;

        const versionSelector = selectedOption.closest('ul').previousElementSibling;
        versionSelector.innerText = selectedOption.innerText;

        const mediaVersionInput = document.getElementById(versionSelector.dataset.mediaVersionTypeField);
        mediaVersionInput.setAttribute('value', selectedOption.mediaVersionValue);
        mediaVersionInput.value = selectedOption.dataset.mediaVersionValue;

        // dispatches media version selected event
        mediaVersionInput.dispatchEvent(new Event('sfs_media.select_version', {bubbles: true}));
    });


    document.addEventListener('change', function (event) {
        if (!event.target.matches('#media_create_form__original_upload')) {
            return;
        }

        document.getElementById('media_create_form_name').value = event.target.files[0].name;

        const originalPreview = document.getElementById('media_create_form__original_preview');

        if (originalPreview && event.target.files.length > 0) {
            const src = URL.createObjectURL(event.target.files[0]);
            const preview = document.createElement('img');
            preview.src = src;
            preview.classList.add('img-fluid');

            originalPreview.innerHTML = '';
            originalPreview.appendChild(preview);
        }
    })
});
