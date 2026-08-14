// ponytail: isolated Karma for documento-nombre-options when full suite does not compile
module.exports = function (config) {
  config.set({
    basePath: '',
    frameworks: ['jasmine', '@angular-devkit/build-angular'],
    plugins: [
      require('karma-jasmine'),
      require('karma-chrome-launcher'),
      require('karma-jasmine-html-reporter'),
      require('karma-coverage'),
      require('@angular-devkit/build-angular/plugins/karma'),
    ],
    client: { jasmine: { random: false }, clearContext: false },
    jasmineHtmlReporter: { suppressAll: true },
    coverageReporter: {
      dir: require('path').join(__dirname, './coverage/documento-nombre-options'),
      subdir: '.',
      reporters: [{ type: 'text-summary' }],
    },
    reporters: ['progress'],
    browsers: ['ChromeHeadless'],
    singleRun: true,
    restartOnFileChange: false,
  });
};
