var gulp = require('gulp');
var less = require('gulp-less');
var path = require('path');
var minifyCSS = require('gulp-minify-css');
var uglify = require('gulp-uglify');
var concat = require('gulp-concat');
var sourcemaps = require('gulp-sourcemaps');
var del = require('del');
var autoprefixer = require('gulp-autoprefixer');
var rename = require("gulp-rename");

gulp.task('js_assets', function() {
  gulp.src('./bower_components/featherlight/release/featherlight.min.js').pipe(gulp.dest('./../js'));
  gulp.src('./bower_components/featherlight/release/featherlight.gallery.min.js').pipe(gulp.dest('./../js'));

  gulp.src('./bower_components/featherlight/release/featherlight.min.css').pipe(gulp.dest('./../js'));
  gulp.src('./bower_components/featherlight/release/featherlight.gallery.min.css').pipe(gulp.dest('./../js'));

});

gulp.task('default', ['js_assets']);