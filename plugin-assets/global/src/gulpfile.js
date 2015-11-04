var gulp = require('gulp');

gulp.task('js_assets', function() {
  gulp.src('./bower_components/featherlight/release/featherlight.min.js').pipe(gulp.dest('./../js'));
  gulp.src('./bower_components/featherlight/release/featherlight.gallery.min.js').pipe(gulp.dest('./../js'));

  gulp.src('./bower_components/featherlight/release/featherlight.min.css').pipe(gulp.dest('./../js'));
  gulp.src('./bower_components/featherlight/release/featherlight.gallery.min.css').pipe(gulp.dest('./../js'));

});

gulp.task('default', ['js_assets']);