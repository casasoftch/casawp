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


var env = process.env.NODE_ENV || 'dev';
//NODE_ENV=production gulp //dev or production

var paths = {
  /*scripts: [
    'js/main.js'
  ],
  fonts: [
  	'bower_components/bootstrap/fonts/*',
  	'bower_components/fontawesome/fonts/*'
  ],
  vendor_scripts: [
    'bower_components/bootstrap/dist/js/bootstrap.min.js'
  ]*/
};

/*gulp.task('js_assets', function() {
	gulp.src(paths.vendor_scripts)
    .pipe(uglify())
    .pipe(concat('assets.min.js'))
    .pipe(gulp.dest('./../js'));
});*/

/*gulp.task('fonts', function() {
  return gulp.src(paths.fonts)
    .pipe(gulp.dest('./../font'));
});
*/
gulp.task('less', function() {
  gulp.src('casasync_template_bs2.less')
    .pipe(less({
      relativeUrls: false
    }))
    .pipe(autoprefixer())
    .pipe(minifyCSS({
      'rebase' : false
    }))
    .pipe(gulp.dest('./../css'))
  ;
});

/*gulp.task('js', function() {
	return gulp.src(paths.scripts)
    .pipe(uglify())
    .pipe(concat('main.min.js'))
    .pipe(gulp.dest('./../js'));
});*/

gulp.task('watch', function(){
	gulp.watch(['less/**/*.less'], ['less']);
	//gulp.watch(paths.scripts, ['js']);
});

gulp.task('default', ['watch', 'less']);

