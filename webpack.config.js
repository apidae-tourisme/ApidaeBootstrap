const path = require('path')
const MoveAssetsPlugin = require("move-assets-webpack-plugin")

module.exports = {
    entry: './src/index.js',
    output: {
        filename: 'apidae.js',
        path: path.resolve(__dirname, 'dist'),
    },
    module: {
        rules: [
            // {
            //     test: /\.css$/i,
            //     use: ['style-loader', 'css-loader'],
            // },
            {
                test: /\.(png|svg|jpg|jpeg|gif)$/i,
                type: 'asset/resource',
            },
            {
                test: /\.(woff|woff2|eot|ttf|otf)$/i,
                type: 'asset/resource',
            },
        ],
    },
};

new MoveAssetsPlugin({
    clean: true,
    patterns: [
        {
            from: 'assets/fonts',
            to: 'dist/fonts'
        },
        {
            from: 'assets/images',
            to: 'dist/images'
        },
    ]
})
