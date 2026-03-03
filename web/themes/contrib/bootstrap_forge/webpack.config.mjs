// webpack.config.mjs
import path from 'path';
import { fileURLToPath } from 'url';
import MiniCssExtractPlugin from 'mini-css-extract-plugin';
import RemoveEmptyScriptsPlugin from 'webpack-remove-empty-scripts';
import { glob } from 'glob';
import jsEntries from './js.entries.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Find all SCSS files in ./scss, except _*.scss
const scssFiles = glob.sync('./scss/**/*.scss', {
  ignore: ['./scss/**/_*.scss'],
});

const entries = {};

// Add SCSS entries
scssFiles.forEach((file) => {
  const relativePath = path
    .relative('./scss', file)
    .replace(/\.scss$/, '');

  entries[relativePath] = path.resolve(__dirname, file);
});

// Add JavaScript entries from js.entries.mjs
Object.assign(entries, jsEntries);

export default {
  mode: 'development',
  devtool: false,
  entry: entries,
  output: {
    path: path.resolve(__dirname, 'js/bundles'),
    filename: '[name].bundle.js',
    pathinfo: false,
    publicPath: '',
  },
  resolve: {
    fullySpecified: false,
    extensions: ['.js', '.mjs', '.json'],
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
          'sass-loader',
        ],
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader', 'postcss-loader'],
      },
      {
        test: /\.m?js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env']
          }
        }
      }
    ],
  },
  plugins: [
    new RemoveEmptyScriptsPlugin(),
    new MiniCssExtractPlugin({
      filename: '../../css/[name].css',
    }),
  ],
  watchOptions: {
    aggregateTimeout: 300,
    ignored: ['**/*.woff', '**/*.json', '**/*.woff2', '**/*.jpg', '**/*.png', '**/*.svg', 'node_modules', 'images'],
  }
};