var mediaTypeModal = document.getElementById('mediaTypeModal')

if (mediaTypeModal) {
    /**
     * Open modal
     */
    mediaTypeModal.addEventListener('show.bs.modal', function (event) {
        // Button that triggered the modal
        mediaTypeModal.clickedButton = event.relatedTarget
        const mediaInput = document.getElementById(mediaTypeModal.clickedButton.dataset.mediaTypeField);

        var mediaTypeModalBody = mediaTypeModal.querySelector('.modal-body');
        var mediaTypeModalFooter = mediaTypeModal.querySelector('.modal-footer');
        mediaTypeModal.querySelector('[data-media-type-select]').classList.add('disabled');

        mediaTypeModalFooter.style.setProperty('display', 'none');

        mediaTypeModalBody.innerHTML = '<div class="p-5 text-center"><div class="spinner-border text-secondary" role="status">\n' +
            '  <span class="visually-hidden">Loading...</span>\n' +
            '</div></div>';

        // get valid types
        var mediaTypes = mediaInput.getAttribute('data-media-type-types')

        var url = mediaTypeModal.clickedButton.dataset.searchUrl; // + '?page=1&rpp=&order=&text=&' + mediaTypes.split(',').map((v) => 'valid_types%5B%5D=' + v).join('&');
        loadSearchPage(url);
    });

    /**
     * Load modal page with media list
     */
    function loadSearchPage(url) {
        var mediaTypeModalBody = mediaTypeModal.querySelector('.modal-body');
        var mediaTypeModalFooter = mediaTypeModal.querySelector('.modal-footer');

        var http_request = new XMLHttpRequest();
        http_request.onreadystatechange = function () {
            if (http_request.readyState === 4) {
                mediaTypeModalBody.innerHTML = http_request.response;
                mediaTypeModalFooter.style.setProperty('display', '');

                mediaTypeModal.querySelector('[data-media-type-select]').classList.add('disabled');

                var searchForm = mediaTypeModalBody.querySelector('form');
                searchForm.onsubmit = function (event) {
                    event.preventDefault();

                    const formData = new FormData(event.target);
                    const data = [...formData.entries()];
                    const asString = data
                        .map(x => `${encodeURIComponent(x[0])}=${encodeURIComponent(x[1])}`)
                        .join('&');

                    loadSearchPage(searchForm.action + '?' + asString);
                }
            }
        };
        http_request.open('GET', url, true);
        http_request.send();
    }

    /**
     * Click on modal media, to be selected
     */
    document.addEventListener('click', function (event) {
        var media = null;
        if (!event.target || !event.target.hasAttribute('data-media-type')) {
            for (i = 0; i < event.composedPath().length; i++) {
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
     * Click on modal Select button, to commit selection
     */
    document.addEventListener('click', function (event) {
        if (!event.target || !event.target.hasAttribute('data-media-type-select')) return;
        const selectedMedia = mediaTypeModal.querySelector('.sfs-media-selected');
        if (!selectedMedia) {
            return;
        }

        const mediaInput = document.getElementById(mediaTypeModal.clickedButton.dataset.mediaTypeField);

        // clean previews data versions
        for (let key in mediaInput.dataset) {
            if (!key.match(/^mediaVersion/i)) {
                continue;
            }
            delete mediaInput.dataset[key];
        }

        // sets input hidden value and data elements
        mediaInput.value = selectedMedia.dataset.mediaId;
        mediaInput.dataset.mediaType = selectedMedia.dataset.mediaType;
        // propagate data version
        for (let key in selectedMedia.dataset) {
            if (!key.match(/^mediaVersion/i)) {
                continue;
            }
            mediaInput.dataset[key] = selectedMedia.dataset[key];
        }

        // show media name
        document.getElementById(mediaTypeModal.clickedButton.dataset.mediaTypeField + '_text').innerHTML = selectedMedia.dataset.mediaName;

        // show thumbnail on widget
        const widget = document.getElementById(mediaTypeModal.clickedButton.dataset.mediaTypeWidget);
        const thumbnail = widget.querySelector('[data-media-type-thumbnail]');
        if (thumbnail) {
            if (mediaInput.dataset['mediaVersion-_thumbnail']) {
                thumbnail.innerHTML = mediaInput.dataset['mediaVersion-_thumbnail'];
            } else {
                thumbnail.innerHTML = '';
            }
        }

        if (window.bootstrap === undefined) {
            console.error('media-type modal script requires window.bootstrap defined. You can define it with:\n\n' +
                'import * as bootstrap from \'bootstrap\';\n' +
                'window.bootstrap = bootstrap;')
        }

        // hides modal
        window.bootstrap.Modal.getInstance(mediaTypeModal).hide();

        // dispatches media selected event
        mediaInput.dispatchEvent(new Event('sfs_media.selected', {bubbles: true}));
    });

    /**
     * Click on form clean button, to unselect media
     */
    document.addEventListener('click', function (event) {
        if (!event.target || !event.target.hasAttribute('data-media-type-clean')) return;

        const mediaInput = document.getElementById(event.target.dataset.mediaTypeField);

        mediaInput.value = '';
        document.getElementById(event.target.dataset.mediaTypeField + '_text').innerHTML = '';
        var widget = document.getElementById(event.target.dataset.mediaTypeWidget);

        var thumbnail = widget.querySelector('[data-media-type-thumbnail]');
        if (thumbnail) {
            thumbnail.innerHTML = '';
        }

        // propagate data version
        for (let key in mediaInput.dataset) {
            if (!key.match(/^mediaVersion/i)) {
                continue;
            }
            delete mediaInput.dataset[key]
        }

        // dispatches media unselected event
        mediaInput.dispatchEvent(new Event('sfs_media.unselected', {bubbles: true}));
    });
}