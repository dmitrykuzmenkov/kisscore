const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const webpack = require('webpack')
const postcssWillChange = require('postcss-will-change')
const postcssAssets = require('postcss-assets')
const autoprefixer = require('autoprefixer')
const postcssPxtorem = require('postcss-pxtorem')
const path = require('path')

module.exports = {
  cache: true,
  devtool: 'source-map',
  entry: {
    bundle: ['./app/client/main.js', './app/client/main.sass'],
  },
  output: {
    path: path.resolve(__dirname, 'env/var'),
    filename: '[name].js',
    pathinfo: false
  },
  resolve: {
    mainFiles: ['index'],
    alias: {
      app: path.resolve(__dirname, 'app/client'),
      component: path.resolve(__dirname, 'app/client/component'),
      lib: path.resolve(__dirname, 'app/client/lib'),
      asset: path.resolve(__dirname, 'app/client/asset'),
    }
  },
  module: {
    rules: [
       {
        test: /\.m?js$/,
        exclude: /node_modules/,
        use: [
          {
            loader: 'babel-loader'
          }
        ]
      },
      {
        test: /\.jext/,
        use: [
          {
            loader: 'jext-loader'
          }
        ]
      },
      {
        test: /\.(png|jpe?g|gif|svg)$/,
        use: [
          {
            loader: 'url-loader?limit=4096&name=asset/[name].[ext]'
          }
        ]
      },
      {
        test: /\.(eot|svg|ttf|woff|woff2)$/,
        use: [
          {
            loader: 'file-loader?name=asset/fonts/[name].[ext]'
          }
        ]
      },
      {
        test: /\.(s?css|sass)/,
        use: [
          'style-loader',
          MiniCssExtractPlugin.loader,
          'css-loader',
          {
            loader: 'postcss-loader',
            options: {
              plugins: [
                postcssWillChange(),
                postcssAssets({basePath: './app/static/img'}),
                autoprefixer({browsers: [
                  'last 2 versions',
                  'IE >= 9',
                  'opera 12',
                  'safari 7',
                  'Android >= 4',
                  'iOS >= 7'
                ]}),
                postcssPxtorem()
              ]
            }
          }, {
            loader: 'sass-loader'
          }
        ]
      }
    ]
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: '[name].css',
			chunkFilename: '[id].css'
    })
  ],
  watchOptions: {
    poll: 1000,
    aggregateTimeout: 500
  },
  optimization: {
    minimizer: [
      // we specify a custom UglifyJsPlugin here to get source maps in production
      new UglifyJsPlugin({
        cache: true,
        parallel: true,
        uglifyOptions: {
          compress: false,
          ecma: 6,
          mangle: true
        },
        sourceMap: true
      })
    ]
  }
}
