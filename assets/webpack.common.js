import {resolve, dirname} from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

export default {
    entry: {
        lizmap: './src/index.js',
        map: './src/legacy/map.js',
        attributeTable: './src/legacy/attributeTable.js',
        edition: './src/legacy/edition.js',
        filter: './src/legacy/filter.js',
        atlas: './src/legacy/atlas.js',
        'switcher-layers-actions': './src/legacy/switcher-layers-actions.js',
        timemanager: './src/legacy/timemanager.js',
        view: './src/legacy/view.js',
        'bottom-dock': './src/legacy/bottom-dock.js',
        'map-projects': './src/legacy/map-projects.js',
    },
    output: {
        filename: '[name].js',
        chunkFilename: '[name].bundle.js',
        publicPath: '/assets/js/',
        path: resolve(__dirname, '../lizmap/www/assets/js/')
    },
    module: {
        rules: [
            {
                test: /\.svg$/,
                use: [
                    'svg-sprite-loader',
                    'svgo-loader'
                ]
            }
        ]
    }
};
