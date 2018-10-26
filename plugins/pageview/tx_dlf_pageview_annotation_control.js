/**
 * (c) Kitodo. Key to digital objects e.V. <contact@kitodo.org>
 *
 * This file is part of the Kitodo and TYPO3 projects.
 *
 * @license GNU General Public License version 3 or later.
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/**
 * This is necessary to support the scrolling of the element into the viewport
 * in case of text hover on the map.
 *
 * @param elem
 * @param speed
 * @returns {jQuery}
 */


if (jQuery.fn.scrollTo === undefined) {
	jQuery.fn.scrollTo = function(elem, speed) {
	    var manualOffsetTop = $(elem).parent().height() / 2;
	    $(this).animate({
	        scrollTop:  $(this).scrollTop() - $(this).offset().top + $(elem).offset().top - manualOffsetTop
	    }, speed == undefined ? 1000 : speed);
	    return this;
	};
}

class DlfAnnotationControl {
	
	constructor(map, image, annotationLists) {
		
		this.map = map;
		
		this.image = image;
		
		this.annotationLists = annotationLists.annotationLists;
		
		this.canvas = annotationLists.canvas;
		
		this.annotationData;		
		
		this.dic = $('#tx-dlf-tools-annotation').length > 0 && $('#tx-dlf-tools-fulltext').attr('data-dic') ?
	        dlfUtils.parseDataDic($('#tx-dlf-tools-fulltext')) :
	        {'fulltext-anno-on':'Activate Fulltext','fulltext-anno-off':'Dectivate Fulltext'};
	        
        this.layers_ = {
            annotationList: new ol.layer.Vector({
                'source': new ol.source.Vector(),
                'style': dlfViewerOL3Styles.defaultStyle()
            }),
            annotation: new ol.layer.Vector({
                'source': new ol.source.Vector(),
                'style': dlfViewerOL3Styles.invisibleStyle()
            }),
            select: new ol.layer.Vector({
                'source': new ol.source.Vector(),
                'style': dlfViewerOL3Styles.selectStyle()
            }),
            hoverAnnotationList: new ol.layer.Vector({
                'source': new ol.source.Vector(),
                'style': dlfViewerOL3Styles.hoverStyle()
            }),
            hoverAnnotation: new ol.layer.Vector({
                'source': new ol.source.Vector(),
                'style': dlfViewerOL3Styles.textlineStyle()
            }),
        };
        
        this.handlers = {
        	mapClick: $.proxy(function(event){
                var feature = this.map.forEachFeatureAtPixel(event['pixel'], function(feature, layer) {
                    if (feature.get('type') === 'annotationList') {
                        return feature;
                    }
                });

                if (feature === undefined) {
                    this.layers_.select.getSource().clear();
                    this.selectedFeature_ = undefined;
                    this.showAnnotationText(undefined);
                    return;
                };
                if (this.selectedFeature_) {
                    // remove old clicks
                    this.layers_.select.getSource().removeFeature(this.selectedFeature_);
                }
                
                if (feature) {

                    // remove hover for preventing an adding of styles
                    this.layers_.hoverAnnotationList.getSource().clear();

                    // add feature
                    this.layers_.select.getSource().addFeature(feature);

                }
                this.selectedFeature_ = feature;


                if (dlfUtils.exists(feature)) {
                    this.showAnnotationText([feature]);
                }
                
                
        	}, this),
           	mapHover: $.proxy(function(event){
                // hover in case of dragging
                if (event['dragging']) {
                    return;
                };

                var hoverSourceAnnotation = this.layers_.hoverAnnotation.getSource(),
                	hoverSourceAnnotationList = this.layers_.hoverAnnotationList.getSource(),
                    selectSource = this.layers_.select.getSource(),
                    map_ = this.map,
                    annotationListFeature,
                    annotationFeature;

                map_.forEachFeatureAtPixel(event['pixel'], function(feature, layer) {
                    if (feature.get('type') === 'annotationList') {
                    	annotationListFeature = feature;
                    }
                    if (feature.get('type') === 'annotation') {
                        annotationFeature = feature;
                    }
                });

                //
                // Handle TextBlock elements
                //
                var activeSelectAnnotationListEl = selectSource.getFeatures().length > 0 ?
                        selectSource.getFeatures()[0] : undefined,
                    activeHoverAnnotationListEl = hoverSourceAnnotationList.getFeatures().length > 0 ?
                        hoverSourceAnnotationList.getFeatures()[0] : undefined,
                    isFeatureEqualSelectFeature = activeSelectAnnotationListEl !== undefined && annotationListFeature !== undefined &&
                    activeSelectAnnotationListEl.getId() === annotationListFeature.getId() ? true : false,
                    isFeatureEqualToOldHoverFeature = activeHoverAnnotationListEl !== undefined && annotationListFeature !== undefined &&
                    activeHoverAnnotationListEl.getId() === annotationListFeature.getId() ? true : false;

                if (!isFeatureEqualToOldHoverFeature && !isFeatureEqualSelectFeature) {

                    // remove old textblock hover features
                    hoverSourceAnnotationList.clear();

                    if (annotationListFeature) {
                        // add textblock feature to hover
                        hoverSourceAnnotationList.addFeature(annotationListFeature);
                    }

                }

                //
                // Handle TextLine elements
                //
                var activeHoverAnnotationListEl = hoverSourceAnnotation.getFeatures().length > 0 ?
                        hoverSourceAnnotation.getFeatures()[0] : undefined,
                    isFeatureEqualToOldHoverFeature = activeHoverAnnotationListEl !== undefined && annotationFeature !== undefined &&
                    activeHoverAnnotationListEl.getId() === annotationFeature.getId() ? true : false;

                if (!isFeatureEqualToOldHoverFeature) {

                    if (activeHoverAnnotationListEl) {

                        // remove highlight effect on fulltext view
                        var oldTargetElem = $('#' + activeHoverAnnotationListEl.getId());

                        if (oldTargetElem.hasClass('highlight') ) {
                            oldTargetElem.removeClass('highlight');
                        }

                        // remove old textline hover features
                        hoverSourceAnnotation.clear();

                    }

                    if (annotationFeature) {

                        // add highlight effect to fulltext view
                        var targetElem = $('#' + annotationFeature.getId());

                        if (targetElem.length > 0 && !targetElem.hasClass('highlight')) {
                            targetElem.addClass('highlight');
                            $('#tx-dlf-fulltextselection').scrollTo(targetElem, 50);
                            hoverSourceAnnotation.addFeature(annotationFeature);
                        }

                    }

                }
           	}, this)
        };

        var anchorEl = $('#tx-dlf-tools-annotation');
        if (anchorEl.length > 0){
            var toogleFulltext = $.proxy(function(event) {
            	  event.preventDefault();

            	  if ($(event.target).hasClass('active')){
            		  this.deactivate();
            		  return;
            	  }

            	  this.activate();
              }, this);

            anchorEl.on('click', toogleFulltext);
            anchorEl.on('touchstart', toogleFulltext);
        }
        
        
        this.selectedFeature_ = undefined;

        // set initial title of fulltext element
        $("#tx-dlf-tools-annotation")
        	.text(this.dic['fulltext-anno-on'])
        	.attr('title', this.dic['fulltext-anno-on']);

        // if fulltext is activated via cookie than run activation methode
        if (dlfUtils.getCookie("tx-dlf-pageview-fulltext-select") == 'enabled') {
        	// activate the fulltext behavior
        	this.activate(anchorEl);
        }
	}
	
	
	showAnnotationText(featuresParam) {
		var features = featuresParam === undefined ? this.annotationData : featuresParam;
	    if (features !== undefined) {
			$('#tx-dlf-fulltextselection').children().remove();
	        for (var i = 0; i < features.length; i++) {
	        	var feature = features[i],
	        		annotations = feature.get('annotations'),
	        	    labelEl;
	        	if (feature.get('label') != '') {
		        	labelEl = $('<span class="annotation-list-label"/>');
	        		labelEl.text(feature.get('label'));
	        		$('#tx-dlf-fulltextselection').append(labelEl);
	        	}
				for (var j=0; j<annotations.length; j++) {
					/*
					 * In contrast to XML attributes, string values in JSON may contain characters like <. Just joining the 
					 * text content and appending the result afterwards as in dlfViewerFullTextControl.showFulltext()
					 * will result in errors (and also allows the introduction of perfectly working <script> tags through annotations.)    
					 */     
		        	var span = $('<span class="annotation" id="' + annotations[j].getId() + '"/>');
		        	span.text(annotations[j].get('content'));
		        	$('#tx-dlf-fulltextselection').append(span);
		        	$('#tx-dlf-fulltextselection').append(' ');
				}
				$('#tx-dlf-fulltextselection').append('<br /><br />');
	        }
	    }
	}
	
	
	
	activate() {
		
		var controlEl = $('#tx-dlf-tools-annotation');

		// if the activate method is called for the first time fetch
		// fulltext data from server
		if (this.annotationData === undefined)  {
			this.annotationData = this.fetchAnnotationListsFromServer(this.annotationLists, this.image, this.canvas);

	        if (this.annotationData !== undefined) {
	        	
	        	this.layers_.annotationList.getSource().addFeatures(this.annotationData);
	        	for (var dataIndex = 0; dataIndex < this.annotationData.length; dataIndex++) {
		    		this.layers_.annotation.getSource().addFeatures(this.annotationData[dataIndex].getAnnotations());
	        	}
	        	
	    		if (this.annotationData.length >0)
	    		{
	    	        this.showAnnotationText(this.annotationData);
//	    	        this.layers_.select.getSource().addFeature(this.annotationData[0]);
//	    	        this.selectedFeature_ = this.annotationData[0];
	    		}
	        	
	            // add features to fulltext layer
	            //this.layers_.textline.getSource().addFeatures(this.annotationData.getTextlines());

	    	    // add first feature of textBlockFeatures to map
	        }
		}

		// now activate the fulltext overlay and map behavior
	    this.enableAnnotationSelect();
	    dlfUtils.setCookie("tx-dlf-pageview-fulltext-select", 'enabled');
	    $(controlEl).addClass('active');

	    // trigger event
	    $(this).trigger("activate-fulltext", this);
		
	}
	
	deactivate() {

		var controlEl = $('#tx-dlf-tools-annotation');

		// deactivate fulltext
		this.disableAnnotationSelect();
	    dlfUtils.setCookie("tx-dlf-pageview-fulltext-select", 'disabled');
	    $(controlEl).removeClass('active');

	    // trigger event
	    $(this).trigger("deactivate-fulltext", this);
	};
	
	disableAnnotationSelect() {

	    // register event listeners
	    this.map.un('click', this.handlers.mapClick);
	    this.map.un('pointermove', this.handlers.mapHover);

	    // remove layers
	    for (var key in this.layers_) {
	        if (this.layers_.hasOwnProperty(key)) {
	            this.map.removeLayer(this.layers_[key]);
	        }
	    };

	    var className = 'fulltext-visible';
	    $("#tx-dlf-tools-annotation").removeClass(className)
	        .text(this.dic['fulltext-anno-on'])
	        .attr('title', this.dic['fulltext-anno-on']);

	    $('#tx-dlf-fulltextselection').removeClass(className);
	    $('#tx-dlf-fulltextselection').hide();
	    $('body').removeClass(className);

	};

	
	enableAnnotationSelect(textBlockFeatures, textLineFeatures) {

	    // register event listeners
	    this.map.on('click', this.handlers.mapClick);
	    this.map.on('pointermove', this.handlers.mapHover);

	    // add layers to map
	    for (var key in this.layers_) {
	        if (this.layers_.hasOwnProperty(key)) {
	            this.map.addLayer(this.layers_[key]);
	        }
	    };

	    // show fulltext container
	    var className = 'fulltext-visible';
	    $("#tx-dlf-tools-annotation").addClass(className)
	      .text(this.dic['fulltext-anno-off'])
	      .attr('title', this.dic['fulltext-anno-off']);

	    $('#tx-dlf-fulltextselection').addClass(className);
	    $('#tx-dlf-fulltextselection').show();
	    $('body').addClass(className);
	}
	
	
	fetchAnnotationListsFromServer(annotationLists, image, canvas, optOffset) {
		var annotationListData = [],
			parser;
    	parser = new DlfIiifAnnotationParser(image, canvas.width, canvas.height, optOffset);
		annotationLists.forEach(function(annotationList){
			var responseJson;
		    var request = $.ajax({
		        url: annotationList.uri,
		        async: false
		    });
		    responseJson = request.responseJSON != null ? request.responseJSON : request.responseText != null ? $.parseJSON(request.responseText) : null;
		    if (responseJson.label === undefined) {
		    	responseJson.label = annotationList.label;
		    }
		    annotationListData.push(parser.parseAnnotationList(responseJson, canvas.id));
		});
		return annotationListData;
	}
	
}