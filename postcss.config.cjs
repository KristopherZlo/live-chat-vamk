module.exports = {
  plugins: [
    require('postcss-discard-duplicates'),
    require('postcss-discard-overridden'),
    require('postcss-merge-longhand'),
    require('postcss-merge-rules'),
  ],
};
