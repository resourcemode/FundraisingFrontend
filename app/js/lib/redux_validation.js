'use strict';

var objectAssign = require( 'object-assign' ),
	_ = require( 'lodash' ),

	ValidationDispatcher = {
		validationFunction: null,
		actionCreationFunction: null,
		fields: null,

		// internal properties
		previousFieldValues: {},
		dispatchIfChanged: function ( formValues, store ) {
			var selectedValues = _.pick( formValues, this.fields ),
				validationResult;

			if ( _.isEqual( this.previousFieldValues, selectedValues ) ) {
				return;
			}

			this.previousFieldValues = selectedValues;
			validationResult = this.validationFunction( selectedValues );
			return store.dispatch( this.actionCreationFunction( validationResult ) );
		}
	},

	ValidationDispatcherCollection = {
		dispatchers: [],
		store: null,
		onUpdate: function () {
			var formContent = this.store.getState().formContent,
				i;
			for ( i = 0; i < this.dispatchers.length; i++ ) {
				this.dispatchers[ i ].dispatchIfChanged( formContent, this.store );
			}
		}
	},

	/**
	 *
	 * @param {Function|Object} validator Function or object that has a 'validate' method.
	 * 			The method will be bound to the object.
	 * @param {Function} actionCreationFunction
	 * @param {Array} fieldNames Names of the state values from formContent that will be validated
	 * @return {ValidationDispatcher}
	 */
	createValidationDispatcher = function ( validator, actionCreationFunction, fieldNames ) {
		if ( typeof validator === 'object' ) {
			validator = validator.validate.bind( validator );
		}
		return objectAssign( Object.create( ValidationDispatcher ), {
			validationFunction: validator,
			actionCreationFunction: actionCreationFunction,
			fields: fieldNames
		} );
	},

	/**
	 *
	 * @param {Object} store Redux store
	 * @param {ValidationDispatcher[]} dispatchers
	 */
	createValidationDispatcherCollection = function ( store, dispatchers ) {
		var collection = objectAssign( Object.create( ValidationDispatcherCollection ), {
				store: store,
				dispatchers: dispatchers
			} );
		store.subscribe( collection.onUpdate.bind( collection ) );
		return collection;
	};

module.exports = {
	createValidationDispatcher: createValidationDispatcher,
	createValidationDispatcherCollection: createValidationDispatcherCollection
};
