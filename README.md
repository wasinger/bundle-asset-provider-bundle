WasingerBundleAssetProviderBundle
=================================

A Symfony bundle containing console commands needed to include
web assets from bundles into the Webpack Encore build:

- `assets:sources`: Loops through all installed bundles.
If a bundle provides an `assets` folder, copies or symlinks this folder
to `assets/bundles/<bundle-name>` in the project root dir.

- `assets:dependencies`: Loops through all installed bundles.
If a bundle provides a `package.json` file, reads the "dependencies" section
of this file, compares it to the dependencies in the package.json file
in the project root dir, and lists missing or incompatible dependencies.

