// webpack.config.mjs
import path from 'path';
import { fileURLToPath } from 'url';
import MiniCssExtractPlugin from 'mini-css-extract-plugin';
import RemoveEmptyScriptsPlugin from 'webpack-remove-empty-scripts';
import { glob } from 'glob'

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// ---------------------------------------------------------------------------
// Entry points
// ---------------------------------------------------------------------------

const entries = {};

// 1. Global SCSS in scss/ → css/ (e.g. scss/style.scss → css/style.css)
const scssFiles = glob.sync('./scss/**/*.scss', {
  ignore: ['./scss/**/_*.scss'],
});

scssFiles.forEach((file) => {
  const relativePath = path
    .relative('./scss', file)
    .replace(/\.scss$/, '');

  // Prefix with css/ so output lands in the css directory.
  entries[`css/${relativePath}`] = path.resolve(__dirname, file);
});

// 2. Component SCSS in components/ → compiled in-place
//    (e.g. components/hero-carousel/hero-carousel.scss → components/hero-carousel/hero-carousel.css)
const componentFiles = glob.sync('./components/**/*.scss', {
  ignore: ['./components/**/_*.scss'],
});

componentFiles.forEach((file) => {
  const relativePath = file.replace(/^\.\//, '').replace(/\.scss$/, '');
  entries[relativePath] = path.resolve(__dirname, file);
});

export default {
  mode: 'development',
  entry: entries,
  output: {
    // Output relative to theme root so both css/ and components/ paths resolve correctly.
    path: path.resolve(__dirname),
    pathinfo: false,
    publicPath: '',
  },
  module: {
    rules: [
      {
        test: /\.scss$/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              url: false,
            },
          },
          'postcss-loader',
          {
            loader: 'sass-loader',
            options: {
              // Use the legacy API so includePaths works with @import.
              api: 'legacy',
              sassOptions: {
                // Allow component SCSS to @import "component-base" from the scss/ dir.
                includePaths: [path.resolve(__dirname, 'scss')],
              },
            },
          },
        ],
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader', 'postcss-loader'],
      },
    ],
  },
  plugins: [
    new RemoveEmptyScriptsPlugin(),
    new MiniCssExtractPlugin({
      filename: '[name].css',
    }),
  ],
  watchOptions: {
    aggregateTimeout: 300,
    ignored: ['**/*.woff', '**/*.json', '**/*.woff2', '**/*.jpg', '**/*.png', '**/*.svg', 'node_modules', 'images'],
  }
};
