import {access, cp, mkdir, readdir, rm, writeFile} from 'node:fs/promises';
import {constants} from 'node:fs';
import {dirname, relative, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const {compile} = await import('sass');

const assetsRoot = fileURLToPath(new URL('.', import.meta.url));
const bundleRoot = resolve(assetsRoot, '..');
const distRoot = resolve(assetsRoot, 'dist');
const loadPaths = [resolve(bundleRoot, 'node_modules')];

await rm(distRoot, {recursive: true, force: true});
await mkdir(distRoot, {recursive: true});

for (const entry of ['scripts', 'images']) {
  const source = resolve(assetsRoot, entry);
  try {
    await access(source, constants.F_OK);
    await cp(source, resolve(distRoot, entry), {recursive: true});
  } catch {
    // Optional asset folders are skipped.
  }
}

async function compileScssFiles(sourceDir) {
  const items = await readdir(sourceDir, {withFileTypes: true});

  for (const item of items) {
    const sourcePath = resolve(sourceDir, item.name);

    if (item.isDirectory()) {
      await compileScssFiles(sourcePath);
      continue;
    }

    if (!item.isFile() || !item.name.endsWith('.scss') || item.name.startsWith('_')) {
      continue;
    }

    const css = compile(sourcePath, {
      style: 'expanded',
      loadPaths: [assetsRoot, ...loadPaths],
    }).css;

    const targetPath = resolve(distRoot, relative(assetsRoot, sourcePath).replace(/\.scss$/, '.css'));
    await mkdir(dirname(targetPath), {recursive: true});
    await writeFile(targetPath, css);
  }
}

await compileScssFiles(resolve(assetsRoot, 'styles'));
