var Encore = require('@symfony/webpack-encore');
var PurgeCssPlugin = require('purgecss-webpack-plugin');
var glob = require('glob-all');
var path = require('path');

if (!Encore.isRuntimeEnvironmentConfigured()) {
        Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or sub-directory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Add 1 entry for each "page" of your app
     * (including one that's included on every page - e.g. "app")
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry('app', './assets/js/app.js')
    .addEntry('nakkikone', './assets/js/nakkikone.js')
    .addEntry('artist_signup', './assets/js/artist_signup.js')
    .addEntry('signature', './assets/js/signature.js')
    //.addEntry('page1', './assets/js/page1.js')
    //.addEntry('page2', './assets/js/page2.js')

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // uncomment if you use TypeScript
    //.enableTypeScriptLoader()

    // uncomment if you use Sass/SCSS files
    .enableSassLoader()

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()
    .copyFiles({
        from: './assets/images',
        // optional target path, relative to the output dir
        to: '../images/[path][name].[ext]',
     })
//     .addPlugin(new PurgeCssPlugin({
//         paths: glob.sync([
//             path.join(__dirname, 'templates/**/*.html.twig')
//         ]),
//         content: ["**/*.twig"],
//         defaultExtractor: (content) => {
//             return content.match(/[\w-/:]+(?<!:)/g) || [];
//         },
//         safelist: {
//             standard: [],
//             deep: [],
//             greedy: [/info$/, /success$/, /warning$/, /danger$/, /fa/]
//         }
//     }))   
;

module.exports = Encore.getWebpackConfig();

