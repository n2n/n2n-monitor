'use strict'

const path = require('path')

module.exports = {
	mode: 'production',
	entry: './monitor.ts',
	output: {
		filename: 'monitor.js',
		path: path.resolve(__dirname, '..', 'src', 'public', 'assets', 'n2n-monitor')
	},
	resolve: {
		extensions: ['.ts', '.js']
	},
	module: {
		rules: [
			{
				test: /\.ts$/,
				use: 'ts-loader',
				exclude: /node_modules/
			}
		]
	}
}
