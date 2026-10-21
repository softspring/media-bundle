/** Intersect the field's allow-list with the representations actually available on a media. */
export function getAllowedMediaSelections(media, mediaTypes) {
    const allowed = mediaTypes?.[media.dataset.mediaType] || {};
    const modes = {
        image: ['image', 'image'],
        video: ['video', 'video'],
        picture: ['picture', 'picture'],
        video_sets: ['video-set', 'videoSet'],
        videoSet: ['video-set', 'videoSet'],
    };
    const selections = new Map();
    for (const [mode, configuredNames] of Object.entries(allowed)) {
        if (!modes[mode]) continue;
        const [attribute, prefix] = modes[mode];
        const names = typeof configuredNames === 'string' ? [configuredNames] : configuredNames;
        for (const name of names || []) {
            if (media.hasAttribute(`data-media-${attribute}-${name}`)) {
                selections.set(`${prefix}#${name}`, {value: `${prefix}#${name}`, label: name});
            }
        }
    }
    return [...selections.values()];
}

export function updateMediaVersionSelectorVisibility(selector, choiceCount) {
    selector.classList.toggle('d-none', choiceCount <= 1);
}
