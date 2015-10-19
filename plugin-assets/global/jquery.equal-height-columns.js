/**
 * Set all passed elements to the same height as the highest element.
 * 
 * Copyright (c) 2012 Ewen Elder
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 *
 * @author: Ewen Elder <glomainn at yah0o d0t c0 dot uk> <ewen at jainaewen d0t-com>
 * @version: 2.0
 */ 
'use strict';

;(function($)
{
	$.equalHeightColumns = {
		version : 2.0
	};
	
	
	
	
	/**
	 * Default plugin options.
	 * 
	 * @access public
	 */
	$.equalHeightColumns.defaults = {
		speed : 0,
		height : 0,
		minHeight : 0,
		maxHeight : 0
	};
	
	
	
	
	/**
	 * Resize columns
	 * 
	 * Extend-able method for performing the column resizing, the 'this' context of this method is
	 * the jQuery collection of elements that will be resized (set using $.proxy()), This method is
	 * part of the default options for simple extending or overriding if desired.
	 * 
	 * @access public
	 */
	$.equalHeightColumns.defaults.resize = function()
	{
		var options = $(this).data('equalHeightColumns.options'),
			height = +options.height,
			currentHeight;
		
		
		// If options.height is false, then find which element is the highest.
		if (!height)
		{
			$(this).each(function()
			{
				currentHeight = $(this).height();
				
				// Test natural height.
				$(this).css('height', 'auto');
				
				
				// If this element's height is more than is store in 'height' then update 'height'.
				if ($(this).height() > height)
				{
					height = $(this).height();
				}
				
				
				// Restore current height - this is mainly to stop animated height changes screwing up.
				$(this).height(currentHeight);
			});
		}
		
		
		// Enforce min height.
		height = (options.minHeight && height < options.minHeight) ? options.minHeight : height;
		
		
		// Enforce max height.
		height = (+options.maxHeight && height > +options.maxHeight) ? +options.maxHeight : height;
		
		
		// Animate the column's height change.
		$(this).animate({height : height}, +options.speed);
	};
	
	
	
	
	/**
	 * Perform actions
	 * 
	 * Extend-able method that deals with destroy, refresh and option getter and setter, Return boolean 
	 * false to prevent the plugin continuing through it's normal process, returning true will allow it 
	 * to continue through as normal unless the action is 'option' and you are getting the option value; 
	 * in this case it will abort the plugin's process,
	 * 
	 * Simply extend this method when setting the options to add your own actions or replace any of the
	 * existing actions.
	 * 
	 * @param {String} action The action to be performed.
	 * @param {String} option Optional parameter specifying an option to get or set.
	 * @param {Mixed} value Value to be used when setting option.
	 * @return {Mixed} Return boolean false to stop the plugin's normal resize functionality after the action is complete.
	 * 
	 * @access public
	 */
	$.equalHeightColumns.defaults.actions = function(action, option, value)
	{
		var options = $(this).data('equalHeightColumns.options'),
			height;
		
		
		switch (action)
		{
			case 'option' :
				if (options && typeof options[option] !== 'undefined')
				{
					if (typeof value !== 'undefined')
					{
						options[option] = value;
						
						$(this).data('equalHeightColumns.options', options);
					}
					
					else
					{
						return options[option];
					}
					
				}
				
				return false;
				break;
			
			case 'destroy' :
				$(this)
					.removeData('equalHeightColumns.options')
					.each(function()
					{
						height = $(this).data('equalHeightColumns.originalHeight');
						
						if (height)
						{
							$(this).height(height);
						}
					});
				
				return false;
				break;
			
			case 'refresh' :
				return true;
				break;
			
			default :
				return false;
				break;
		}
	};
	
	
	
	
	/**
	 * The Equal Height Column plugin
	 * 
	 * Check whether to apply the plugin - or perform an action like set option or refresh etc.
	 * 
	 * @param {Mixed} options Either the options object when initiating the plugin or the name of an action to perform.
	 * @param {String} option The name of an option to get or set.
	 * @param {Mixed} value The value that the option specified in the 'option' argument should be.
	 */
	$.fn.equalHeightColumns = function(options, option, value)
	{
		var action = typeof options === 'string' ? options : false,
			method,
			resize,
			height;
		
		
		// If an action is being requested then call the actions method.
		if (action)
		{
			options = $.extend({}, $.equalHeightColumns.defaults, $(this).data('equalHeightColumns.options'));
			
			method = $.proxy(options.actions, this);
			
			
			if (action === 'option' && typeof value === 'undefined')
			{
				return method(action, option);
			}
			
			else if (method(action, option, value) === false)
			{
				return $(this);
			}
		}
		
		
		options = $.extend({}, $.equalHeightColumns.defaults, options);
		
		$(this).data('equalHeightColumns.options', options);
		
		
		// Store the original height of each passed element for use in the destroy action.
		$(this).each(function()
		{
			if (typeof $(this).data('equalHeightColumns.originalHeight') === 'undefined')
			{
				$(this).data('equalHeightColumns.originalHeight', $(this).height());
			}
		});
		
		
		method = $.proxy(options.resize, this);
		
		method();
		
		
		return $(this);
	};
})(jQuery);