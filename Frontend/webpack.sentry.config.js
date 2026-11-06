const { execSync } = require('child_process');
const { sentryWebpackPlugin } = require('@sentry/webpack-plugin');

function gitShortSha() {
  try {
    return execSync('git rev-parse --short HEAD', { encoding: 'utf8' }).trim();
  } catch {
    return 'unknown';
  }
}

/** @type {import('@angular-builders/custom-webpack').CustomWebpackConfig} */
module.exports = (config, options) => {
  if (options.configuration !== 'production') {
    return config;
  }

  const authToken = process.env.SENTRY_AUTH_TOKEN;
  if (!authToken) {
    // ponytail: build prod sin token sigue generando hidden maps locales; upload solo en CI
    console.warn('[sentry] SENTRY_AUTH_TOKEN no definido — no se suben source maps');
    return config;
  }

  const release =
    process.env.SENTRY_RELEASE || `frontend@${gitShortSha()}`;

  config.plugins = config.plugins || [];
  config.plugins.push(
    sentryWebpackPlugin({
      org: 'smartpyme',
      project: 'smartpyme-frontend',
      authToken,
      release: { name: release },
      sourcemaps: {
        filesToDeleteAfterUpload: ['**/*.map'],
      },
      telemetry: false,
    }),
  );

  return config;
};
