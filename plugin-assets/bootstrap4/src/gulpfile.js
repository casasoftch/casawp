var gulp = require('gulp');
var sass = require('gulp-sass');
var path = require('path');
var minifyCSS = require('gulp-minify-css');
var uglify = require('gulp-uglify');
var concat = require('gulp-concat');
var sourcemaps = require('gulp-sourcemaps');
var del = require('del');
var autoprefixer = require('gulp-autoprefixer');
var gulpIgnore = require('gulp-ignore');
var rename = require("gulp-rename");


var env = process.env.NODE_ENV || 'dev';
//NODE_ENV=production gulp //dev or production

var paths = {
  scripts: [
    'js/main.js'
  ],
  fonts: [
  	'bower_components/bootstrap/fonts/*',
  	'bower_components/fontawesome/fonts/*'
  ],
  vendor_scripts: [
    'bower_components/bootstrap/dist/js/bootstrap.min.js'
  ]
};

gulp.task('js_assets', function() {
	gulp.src(paths.vendor_scripts)
    .pipe(uglify())
    .pipe(concat('assets.min.js'))
    .pipe(gulp.dest('./../js'));
});

gulp.task('fonts', function() {
  return gulp.src(paths.fonts)
    .pipe(gulp.dest('./../font'));
});

gulp.task('sass', function() {
  gulp.src('sass/bs4.scss')
    .pipe(sass().on('error', sass.logError))
    .pipe(autoprefixer())
    .pipe(minifyCSS({'rebase' : false}))
    .pipe(gulp.dest('./../css'))
  ;
});

gulp.task('js', function() {
	return gulp.src(paths.scripts)
    .pipe(uglify())
    .pipe(concat('main.min.js'))
    .pipe(gulp.dest('./../js'));
});

gulp.task('watch', function(){
	gulp.watch(['sass/**/*.scss'], ['sass']);
	gulp.watch(paths.scripts, ['js']);
});

gulp.task('default', ['watch', 'sass', 'js_assets', 'js', 'fonts']);

