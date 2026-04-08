const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        dashboard: './admin/src/dashboard.js',
        settings: './admin/src/settings.js',
    },
    output: {
        ...defaultConfig.output,
        path: __dirname + '/assets/dist',
    },
};
