import {readFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
import {test} from 'node:test';

const source = await readFile(new URL('../../../assets/scripts/media-selection.js', import.meta.url), 'utf8');
const {getAllowedMediaSelections, updateMediaVersionSelectorVisibility} = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const media = (type, attributes) => ({dataset: {mediaType: type}, hasAttribute: name => attributes.includes(name)});

test('picture-only fields never expose raw image versions', () => {
    const item = media('content', ['data-media-image-_original', 'data-media-image-lg_avif', 'data-media-picture-_default', 'data-media-picture-other']);
    assert.deepEqual(getAllowedMediaSelections(item, {content: {picture: '_default'}}), [{value: 'picture#_default', label: '_default'}]);
});

test('array allow-lists preserve configured order and omit missing choices', () => {
    const item = media('content', ['data-media-picture-_default', 'data-media-picture-other']);
    assert.deepEqual(getAllowedMediaSelections(item, {content: {picture: ['other', 'missing', '_default']}}).map(c => c.value), ['picture#other', 'picture#_default']);
});

test('video and video sets use distinct prefixes', () => {
    const item = media('video', ['data-media-video-_original', 'data-media-video-set-cms_preview']);
    assert.deepEqual(getAllowedMediaSelections(item, {video: {video_sets: 'cms_preview', video: ['_original']}}).map(c => c.value), ['videoSet#cms_preview', 'video#_original']);
});

test('unknown types and empty configuration have no choices', () => {
    assert.deepEqual(getAllowedMediaSelections(media('hero', []), {content: {picture: '_default'}}), []);
    assert.deepEqual(getAllowedMediaSelections(media('content', []), {}), []);
});

test('the selector hides for zero or one choice and reappears for multiple choices', () => {
    const classes = new Set();
    const selector = {classList: {toggle: (name, hidden) => hidden ? classes.add(name) : classes.delete(name)}};
    for (const count of [0, 1, 2, 1, 3, 0]) {
        updateMediaVersionSelectorVisibility(selector, count);
        assert.equal(classes.has('d-none'), count <= 1);
    }
});
