/**
 * jqGrid extension for custom methods
 * Tony Tomov tony@trirand.com, http://trirand.com/blog/
 *
 * Wildraid wildraid@mail.ru
 * Oleg Kiriljuk oleg.kiriljuk@ok-soft-gmbh.com
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl-2.0.html
**/

/*jshint eqeqeq:false */
/*jslint browser: true, devel: true, eqeq: true, nomen: true, plusplus: true, vars: true, unparam: true, white: true, todo: true */
/*global jQuery */
(function ($) {
	"use strict";
	var jgrid = $.jgrid, getGridRes = jgrid.getMethod("getGridRes"), jqID = jgrid.jqID,
		getGuiStyles = function (path, jqClasses) {
			var p = this.p, guiStyle = p.guiStyle || jgrid.defaults.guiStyle || "jQueryUI";
			return jgrid.mergeCssClasses(jgrid.getRes(jgrid.guiStyles[guiStyle], path), jqClasses || "");
		};
	jgrid.extend({
		getColProp: function (colname) {
			var ret = {}, t = this[0], iCol;
			if (t != null && t.grid) {
				iCol = t.p.iColByName[colname];
				if (iCol !== undefined) {
					return t.p.colModel[iCol];
				}
			}
			return ret;
		},
		setColProp: function (colname, obj) {
			//do not set width will not work
			return this.each(function () {
				var self = this, p = self.p, iCol;
				if (self.grid && p != null && obj) {
					iCol = p.iColByName[colname];
					if (iCol !== undefined) {
						$.extend(true, p.colModel[iCol], obj);
					}
				}
			});
		},
		sortGrid: function (colname, reload, sor) {
			return this.each(function () {
				var self = this, grid = self.grid, p = self.p, colModel = p.colModel, l = colModel.length, cm, i, sobj = false, sort;
				if (!grid) { return; }
				if (!colname) { colname = p.sortname; }
				if (typeof reload !== "boolean") { reload = false; }
				for (i = 0; i < l; i++) {
					cm = colModel[i];
					if (cm.index === colname || cm.name === colname) {
						if (p.frozenColumns === true && cm.frozen === true) {
							sobj = grid.fhDiv.find("#" + p.id + "_" + colname);
						}
						if (!sobj || sobj.length === 0) {
							sobj = grid.headers[i].el;
						}
						sort = cm.sortable;
						if (typeof sort !== "boolean" || sort) {
							self.sortData("jqgh_" + p.id + "_" + colname, i, reload, sor, sobj);
						}
						break;
					}
				}
			});
		},
		clearBeforeUnload: function () {
			return this.each(function () {
				var self = this, p = self.p, grid = self.grid, propOrMethod, clearArray = jgrid.clearArray,
					hasOwnProperty = Object.prototype.hasOwnProperty;
				if ($.isFunction(grid.emptyRows)) {
					grid.emptyRows.call(self, true, true); // this work quick enough and reduce the size of memory leaks if we have someone
				}

				$(document).unbind("mouseup.jqGrid" + p.id);
				$(grid.hDiv).unbind("mousemove"); // TODO add namespace
				$(self).unbind();

				/*grid.dragEnd = null;
				grid.dragMove = null;
				grid.dragStart = null;
				grid.emptyRows = null;
				grid.populate = null;
				grid.populateVisible = null;
				grid.scrollGrid = null;
				grid.selectionPreserver = null;
	
				grid.bDiv = null;
				grid.fbRows = null;
				grid.cDiv = null;
				grid.hDiv = null;
				grid.cols = null;*/
				var i, l = grid.headers.length;
				for (i = 0; i < l; i++) {
					grid.headers[i].el = null;
				}
				for (propOrMethod in grid) {
					if (grid.hasOwnProperty(propOrMethod)) {
						grid.propOrMethod = null;
					}
				}

				/*self.formatCol = null;
				self.sortData = null;
				self.updatepager = null;
				self.refreshIndex = null;
				self.setHeadCheckBox = null;
				self.constructTr = null;
				self.formatter = null;
				self.addXmlData = null;
				self.addJSONData = null;
				self.grid = null;*/

				var propOrMethods = ["formatCol", "sortData", "updatepager", "refreshIndex", "setHeadCheckBox", "constructTr", "clearToolbar", "fixScrollOffsetAndhBoxPadding", "rebuildRowIndexes", "modalAlert", "toggleToolbar", "triggerToolbar", "formatter", "addXmlData", "addJSONData", "ftoolbar", "_inlinenav", "nav", "grid", "p"];
				l = propOrMethods.length;
				for (i = 0; i < l; i++) {
					if (hasOwnProperty.call(self, propOrMethods[i])) {
						self[propOrMethods[i]] = null;
					}
				}
				self._index = {};
				clearArray(p.data);
				clearArray(p.lastSelectedData);
				clearArray(p.selarrrow);
				clearArray(p.savedRow);
			});
		},
		GridDestroy: function () {
			return this.each(function () {
				var self = this, p = self.p;
				if (self.grid && p != null) {
					if (p.pager) { // if not part of grid
						$(p.pager).remove();
					}
					try {
						$("#alertmod_" + p.idSel).remove();
						$(self).jqGrid("clearBeforeUnload");
						$(p.gBox).remove();
					} catch (ignore) { }
				}
			});
		},
		GridUnload: function () {
			return this.each(function () {
				var self = this, $self = $(self), p = self.p, $j = $.fn.jqGrid;
				if (!self.grid) { return; }
				$self.removeClass($j.getGuiStyles.call($self, "grid", "ui-jqgrid-btable"));
				// The multiple removeAttr can be replace to one after dropping of support of old jQuery
				if (p.pager) {
					$(p.pager).empty()
						.removeClass($j.getGuiStyles.call($self, "pagerBottom", "ui-jqgrid-pager"))
						.removeAttr("style")
						.removeAttr("dir");
				}
				$self.jqGrid("clearBeforeUnload");
				$self.removeAttr("style")
					.removeAttr("tabindex")
					.removeAttr("role")
					.removeAttr("aria-labelledby")
					.removeAttr("style");
				$self.empty(); // remove the first line
				$self.insertBefore(p.gBox).show();
				$(p.pager).insertBefore(p.gBox).show();
				$(p.gBox).remove();
			});
		},
		setGridState: function (state) {
			return this.each(function () {
				var $t = this, p = $t.p, grid = $t.grid, cDiv = grid.cDiv, $uDiv = $(grid.uDiv), $ubDiv = $(grid.ubDiv);
				if (!grid || p == null) { return; }
				var iconSet = p.iconSet || jgrid.defaults.iconSet || "jQueryUI",
					getMinimizeIcon = function (path) {
						return jgrid.getIconRes(iconSet, "gridMinimize." + path);
					},
					visibleGridIcon = getMinimizeIcon("visible"), // "ui-icon-circle-triangle-n"
					hiddenGridIcon = getMinimizeIcon("hidden");  // "ui-icon-circle-triangle-s"
				if (state === "hidden") {
					$(".ui-jqgrid-bdiv, .ui-jqgrid-hdiv", p.gView).slideUp("fast");
					if (p.pager) { $(p.pager).slideUp("fast"); }
					if (p.toppager) { $(p.toppager).slideUp("fast"); }
					if (p.toolbar[0] === true) {
						if (p.toolbar[1] === "both") {
							$ubDiv.slideUp("fast");
						}
						$uDiv.slideUp("fast");
					}
					if (p.footerrow) { $(".ui-jqgrid-sdiv", p.gBox).slideUp("fast"); }
					$(".ui-jqgrid-titlebar-close span", cDiv).removeClass(visibleGridIcon).addClass(hiddenGridIcon);
					p.gridstate = "hidden";
				} else if (state === "visible") {
					$(".ui-jqgrid-hdiv, .ui-jqgrid-bdiv", p.gView).slideDown("fast");
					if (p.pager) { $(p.pager).slideDown("fast"); }
					if (p.toppager) { $(p.toppager).slideDown("fast"); }
					if (p.toolbar[0] === true) {
						if (p.toolbar[1] === "both") {
							$ubDiv.slideDown("fast");
						}
						$uDiv.slideDown("fast");
					}
					if (p.footerrow) { $(".ui-jqgrid-sdiv", p.gBox).slideDown("fast"); }
					$(".ui-jqgrid-titlebar-close span", cDiv).removeClass(hiddenGridIcon).addClass(visibleGridIcon);
					p.gridstate = "visible";
				}
			});
		},
		filterToolbar: function (oMuligrid) {
			// if one uses jQuery wrapper with multiple grids, then oMultiple specify the object with common options
			return this.each(function () {
				var $t = this, grid = $t.grid, $self = $($t), p = $t.p, bindEv = jgrid.bindEv, infoDialog = jgrid.info_dialog;
				if (this.ftoolbar) { return; }
				// make new copy of the options and use it for ONE specific grid.
				// p.searching can contains grid specific options
				// we will don't modify the input options oMuligrid
				var o = $.extend(true, {
						autosearch: true,
						autosearchDelay: 500,
						searchOnEnter: true,
						beforeSearch: null,
						afterSearch: null,
						beforeClear: null,
						afterClear: null,
						searchurl: "",
						stringResult: false,
						groupOp: "AND",
						defaultSearch: "bw",
						searchOperators: false,
						resetIcon: "x",
						applyLabelClasses: true,
						operands: { "eq": "==", "ne": "!", "lt": "<", "le": "<=", "gt": ">", "ge": ">=", "bw": "^", "bn": "!^", "in": "=", "ni": "!=", "ew": "|", "en": "!@", "cn": "~", "nc": "!~", "nu": "#", "nn": "!#" }
					}, jgrid.search, p.searching || {}, oMuligrid || {}),
					colModel = p.colModel,
					getRes = function (path) {
						return getGridRes.call($self, path);
					},
					errcap = getRes("errors.errcap"),
					bClose = getRes("edit.bClose"),
					editMsg = getRes("edit.msg"),
					hoverClasses = getGuiStyles.call($t, "states.hover"),
					highlightClass = getGuiStyles.call($t, "states.select"),
					dataFieldClass = getGuiStyles.call($t, "filterToolbar.dataField"),
					triggerToolbar = function () {
						var sdata = {}, j = 0, sopt = {};
						$.each(colModel, function () {
							var cm = this, nm = cm.index || cm.name, v, so,
								$elem = $("#gs_" + jqID(cm.name), (cm.frozen === true && p.frozenColumns === true) ? grid.fhDiv : grid.hDiv),
								getFormaterOption = function (optionName, formatter) {
									var formatoptions = cm.formatoptions || {};
									return formatoptions[optionName] !== undefined ?
										formatoptions[optionName] :
										getRes("formatter." + (formatter || cm.formatter) + "." + optionName);
								},
								cutThousandsSeparator = function (val) {
									var separator = getFormaterOption("thousandsSeparator")
											.replace(/([\.\*\_\'\(\)\{\}\+\?\\])/g, "\\$1");
									return val.replace(new RegExp(separator, "g"), "");
								};

							if (o.searchOperators) {
								so = $elem.parent().prev().children("a").data("soper") || o.defaultSearch;
							} else {
								so = (cm.searchoptions && cm.searchoptions.sopt) ? cm.searchoptions.sopt[0] : cm.stype === "select" ? "eq" : o.defaultSearch;
							}
							/* the format of element of the searching toolbar if ANOTHER
							 * as the format of cells in the grid. So one can't use
							 *     value = $.unformat.call($t, $elem, { colModel: cm }, iCol)
							 * to get the value. Even the access to the value should be
							 * $elem.val() instead of $elem.text() used in the common case of
							 * formatter. So we have to make manual conversion of searching filed
							 * used for integer/number/currency. The code will be duplicate */
							if (cm.stype === "custom" && $.isFunction(cm.searchoptions.custom_value) && $elem.length > 0 && $elem[0].nodeName.toUpperCase() === "SPAN") {
								v = cm.searchoptions.custom_value.call($t, $elem.children(".customelement").filter(":first"), "get");
							} else {
								v = $.trim($elem.val());
								switch (cm.formatter) {
									case "integer":
										v = cutThousandsSeparator(v)
												.replace(getFormaterOption("decimalSeparator", "number"), ".");
										if (v !== "") {
											// normalize the strings like "010.01" to "10"
											v = String(parseInt(v, 10));
										}
										break;
									case "number":
										v = cutThousandsSeparator(v)
												.replace(getFormaterOption("decimalSeparator"), ".");
										if (v !== "") {
											// normalize the strings like "010.00" to "10"
											// and "010.12" to "10.12"
											v = String(parseFloat(v));
										}
										break;
									case "currency":
										var prefix = getFormaterOption("prefix"),
											suffix = getFormaterOption("suffix");
										if (prefix && prefix.length) {
											v = v.substr(prefix.length);
										}
										if (suffix && suffix.length) {
											v = v.substr(0, v.length - suffix.length);
										}
										v = cutThousandsSeparator(v)
												.replace(getFormaterOption("decimalSeparator"), ".");
										if (v !== "") {
											// normalize the strings like "010.00" to "10"
											// and "010.12" to "10.12"
											v = String(parseFloat(v));
										}
										break;
									default:
										break;
								}
							}
							if (v || so === "nu" || so === "nn") {
								sdata[nm] = v;
								sopt[nm] = so;
								j++;
							} else {
								try {
									delete p.postData[nm];
								} catch (ignore) { }
							}
						});
						var sd = j > 0 ? true : false;
						if (o.stringResult || o.searchOperators || p.datatype === "local") {
							var ruleGroup = "{\"groupOp\":\"" + o.groupOp + "\",\"rules\":[";
							var gi = 0;
							$.each(sdata, function (cmName, n) {
								//var iCol = p.iColByName[cmName], cm = p.colModel[iCol],
								//	value = $.unformat.call($t, $("<span></span>").text(n), { colModel: cm }, iCol);
								if (gi > 0) { ruleGroup += ","; }
								ruleGroup += "{\"field\":\"" + cmName + "\",";
								ruleGroup += "\"op\":\"" + sopt[cmName] + "\",";
								n += "";
								ruleGroup += "\"data\":\"" + n.replace(/\\/g, "\\\\").replace(/\"/g, "\\\"") + "\"}";
								gi++;
							});
							ruleGroup += "]}";
							$.extend(p.postData, { filters: ruleGroup });
							$.each(["searchField", "searchString", "searchOper"], function (i, n) {
								if (p.postData.hasOwnProperty(n)) { delete p.postData[n]; }
							});
						} else {
							$.extend(p.postData, sdata);
						}
						var saveurl;
						if (p.searchurl) {
							saveurl = p.url;
							$self.jqGrid("setGridParam", { url: p.searchurl });
						}
						var bsr = $self.triggerHandler("jqGridToolbarBeforeSearch") === "stop" ? true : false;
						if (!bsr && $.isFunction(o.beforeSearch)) { bsr = o.beforeSearch.call($t); }
						if (!bsr) {
							$self.jqGrid("setGridParam", { search: sd })
								.trigger("reloadGrid", [$.extend({ page: 1 }, o.reloadGridSearchOptions || {})]);
						}
						if (saveurl) { $self.jqGrid("setGridParam", { url: saveurl }); }
						$self.triggerHandler("jqGridToolbarAfterSearch");
						if ($.isFunction(o.afterSearch)) { o.afterSearch.call($t); }
					},
					clearToolbar = function (trigger) {
						var sdata = {}, j = 0, nm;
						trigger = (typeof trigger !== "boolean") ? true : trigger;
						$.each(colModel, function () {
							var v, cm = this, $elem = $("#gs_" + jqID(cm.name), (cm.frozen === true && p.frozenColumns === true) ? grid.fhDiv : grid.hDiv),
								isSindleSelect;
							if (cm.searchoptions && cm.searchoptions.defaultValue !== undefined) { v = cm.searchoptions.defaultValue; }
							nm = cm.index || cm.name;
							switch (cm.stype) {
								case "select":
									isSindleSelect = $elem.length > 0 ? !$elem[0].multiple : true;
									$elem.find("option").each(function (i) {
										this.selected = i === 0 && isSindleSelect;
										if ($(this).val() === v) {
											this.selected = true;
											return false;
										}
									});
									if (v !== undefined) {
										// post the key and not the text
										sdata[nm] = v;
										j++;
									} else {
										try {
											delete p.postData[nm];
										} catch (ignore) { }
									}
									break;
								case "text":
									$elem.val(v || "");
									if (v !== undefined) {
										sdata[nm] = v;
										j++;
									} else {
										try {
											delete p.postData[nm];
										} catch (ignore) { }
									}
									break;
								case "custom":
									if ($.isFunction(cm.searchoptions.custom_value) && $elem.length > 0 && $elem[0].nodeName.toUpperCase() === "SPAN") {
										cm.searchoptions.custom_value.call($t, $elem.children(".customelement").filter(":first"), "set", v || "");
									}
									break;
							}
						});
						var sd = j > 0 ? true : false;
						p.resetsearch = true;
						if (o.stringResult || o.searchOperators || p.datatype === "local") {
							var ruleGroup = "{\"groupOp\":\"" + o.groupOp + "\",\"rules\":[";
							var gi = 0;
							$.each(sdata, function (i, n) {
								if (gi > 0) { ruleGroup += ","; }
								ruleGroup += "{\"field\":\"" + i + "\",";
								ruleGroup += "\"op\":\"" + "eq" + "\",";
								n += "";
								ruleGroup += "\"data\":\"" + n.replace(/\\/g, "\\\\").replace(/\"/g, "\\\"") + "\"}";
								gi++;
							});
							ruleGroup += "]}";
							$.extend(p.postData, { filters: ruleGroup });
							$.each(["searchField", "searchString", "searchOper"], function (i, n) {
								if (p.postData.hasOwnProperty(n)) { delete p.postData[n]; }
							});
						} else {
							$.extend(p.postData, sdata);
						}
						var saveurl;
						if (p.searchurl) {
							saveurl = p.url;
							$self.jqGrid("setGridParam", { url: p.searchurl });
						}
						var bcv = $self.triggerHandler("jqGridToolbarBeforeClear") === "stop" ? true : false;
						if (!bcv && $.isFunction(o.beforeClear)) { bcv = o.beforeClear.call($t); }
						if (!bcv) {
							if (trigger) {
								$self.jqGrid("setGridParam", { search: sd })
									.trigger("reloadGrid", [$.extend({ page: 1 }, o.reloadGridResetOptions || {})]);
							}
						}
						if (saveurl) { $self.jqGrid("setGridParam", { url: saveurl }); }
						$self.triggerHandler("jqGridToolbarAfterClear");
						if ($.isFunction(o.afterClear)) { o.afterClear(); }
					},
					toggleToolbar = function () {
						var trow = $("tr.ui-search-toolbar", grid.hDiv),
							trow2 = p.frozenColumns === true ? $("tr.ui-search-toolbar", grid.fhDiv) : false;
						if (trow.css("display") === "none") {
							trow.show();
							if (trow2) {
								trow2.show();
							}
						} else {
							trow.hide();
							if (trow2) {
								trow2.hide();
							}
						}
					},
					odata = getRes("search.odata") || [],
					customSortOperations = p.customSortOperations,
					buildRuleMenu = function (elem, left, top) {
						$("#sopt_menu").remove();

						left = parseInt(left, 10);
						top = parseInt(top, 10) + 18;

						var selclass, ina, i = 0, aoprs = [], selected = $(elem).data("soper"), nm = $(elem).data("colname"),
							fs = $(".ui-jqgrid-view").css("font-size") || "11px",
							str = '<ul id="sopt_menu" class="ui-search-menu" role="menu" tabindex="0" style="font-size:' + fs + ";left:" + left + "px;top:" + top + 'px;">';
						i = p.iColByName[nm];
						if (i === undefined) { return; }
						var cm = colModel[i], options = $.extend({}, cm.searchoptions), odataItem, item, itemOper, itemOperand, itemText;
						if (!options.sopt) {
							options.sopt = [];
							options.sopt[0] = cm.stype === "select" ? "eq" : o.defaultSearch;
						}
						$.each(odata, function () { aoprs.push(this.oper); });
						// append aoprs array with custom operations defined in customSortOperations parameter jqGrid
						if (customSortOperations != null) {
							$.each(customSortOperations, function (propertyName) { aoprs.push(propertyName); });
						}
						for (i = 0; i < options.sopt.length; i++) {
							itemOper = options.sopt[i];
							ina = $.inArray(itemOper, aoprs);
							if (ina !== -1) {
								odataItem = odata[ina];
								if (odataItem !== undefined) {
									// standard operation
									itemOperand = o.operands[itemOper];
									itemText = odataItem.text;
								} else if (customSortOperations != null) {
									// custom operation
									item = customSortOperations[itemOper];
									itemOperand = item.operand;
									itemText = item.text;
								}
								selclass = selected === itemOper ? highlightClass : "";
								str += '<li class="ui-menu-item ' + selclass + '" role="presentation"><a class="ui-corner-all g-menu-item" tabindex="0" role="menuitem" value="' + itemOper + '" data-oper="' + itemOperand + '"><table' + (jgrid.msie && jgrid.msiever() < 8 ? ' cellspacing="0"' : '') + '><tr><td style="width:25px">' + itemOperand + '</td><td>' + itemText + '</td></tr></table></a></li>';
							}
						}
						str += "</ul>";
						$("body").append(str);
						$("#sopt_menu").addClass("ui-menu ui-widget ui-widget-content ui-corner-all");
						$("#sopt_menu > li > a").hover(
							function () { $(this).addClass(hoverClasses); },
							function () { $(this).removeClass(hoverClasses); }
						).click(function () {
							var v = $(this).attr("value"),
								oper = $(this).data("oper");
							$self.triggerHandler("jqGridToolbarSelectOper", [v, oper, elem]);
							$("#sopt_menu").hide();
							$(elem).text(oper).data("soper", v);
							if (o.autosearch === true) {
								var inpelm = $(elem).parent().next().children()[0];
								if ($(inpelm).val() || v === "nu" || v === "nn") {
									triggerToolbar();
								}
							}
						});
					},
					timeoutHnd,
					tr = $("<tr class='ui-search-toolbar' role='row'></tr>");

				// create the row
				$.each(colModel, function (ci) {
					var cm = this, soptions, mode = "filter", surl, self, select = "", sot, so, i, searchoptions = cm.searchoptions, editoptions = cm.editoptions,
						th = $("<th role='columnheader' class='" + getGuiStyles.call($t, "colHeaders", "ui-th-column ui-th-" + p.direction + " " + (o.applyLabelClasses ? cm.labelClasses || "" : "")) + "'></th>"),
						thd = $("<div style='position:relative;height:auto;'></div>"),
						stbl = $("<table class='ui-search-table'" + (jgrid.msie && jgrid.msiever() < 8 ? " cellspacing='0'" : "") + "><tr><td class='ui-search-oper'></td><td class='ui-search-input'></td><td class='ui-search-clear' style='width:1px'></td></tr></table>");
					if (this.hidden === true) { $(th).css("display", "none"); }
					this.search = this.search === false ? false : true;
					if (this.stype === undefined) { this.stype = "text"; }
					soptions = $.extend({mode: mode}, searchoptions || {});
					if (this.search) {
						if (o.searchOperators) {
							so = (soptions.sopt) ? soptions.sopt[0] : cm.stype === "select" ? "eq" : o.defaultSearch;
							for (i = 0; i < odata.length; i++) {
								if (odata[i].oper === so) {
									sot = o.operands[so] || "";
									break;
								}
							}
							if (sot === undefined && customSortOperations != null) {
								var customOp;
								for (customOp in customSortOperations) {
									if (customSortOperations.hasOwnProperty(customOp) && customOp === so) {
										sot = customSortOperations[customOp].operand;
										break;
										//soptions.searchtitle = customSortOperations[customOp].title;
									}
								}
							}
							if (sot === undefined) { sot = "="; }
							var st = soptions.searchtitle != null ? soptions.searchtitle : getRes("search.operandTitle");
							select = "<a title='" + st + "' style='padding-right: 0.5em;' data-soper='" + so + "' class='soptclass' data-colname='" + this.name + "'>" + sot + "</a>";
						}
						$("td", stbl).filter(":first").data("colindex", ci).append(select);
						if (soptions.sopt == null || soptions.sopt.length === 1) {
							$("td.ui-search-oper", stbl).hide();
						}
						if (soptions.clearSearch === undefined) {
							soptions.clearSearch = this.stype === "text" ? true : false;
						}
						if (soptions.clearSearch) {
							var csv = getRes("search.resetTitle") || "Clear Search Value";
							$("td", stbl).eq(2).append("<a title='" + csv + "' style='padding-right: 0.2em;padding-left: 0.3em;' class='clearsearchclass'>" + o.resetIcon + "</a>");
						} else {
							$("td", stbl).eq(2).hide();
						}
						switch (this.stype) {
							case "select":
								surl = this.surl || soptions.dataUrl;
								if (surl) {
									// data returned should have already constructed html select
									// primitive jQuery load
									self = thd;
									$(self).append(stbl);
									$.ajax($.extend({
										url: surl,
										dataType: "html",
										success: function (data, textStatus, jqXHR) {
											if (soptions.buildSelect !== undefined) {
												var d = soptions.buildSelect(data, jqXHR);
												if (d) {
													$("td", stbl).eq(1).append(d);
												}
											} else {
												$("td", stbl).eq(1).append(data);
											}
											var $select = stbl.find("td.ui-search-input>select"); // stbl.find(">tbody>tr>td.ui-search-input>select")
											if (soptions.defaultValue !== undefined) { $select.val(soptions.defaultValue); }
											$select.attr({ name: cm.index || cm.name, id: "gs_" + cm.name });
											if (soptions.attr) { $select.attr(soptions.attr); }
											$select.addClass(dataFieldClass);
											$select.css({ width: "100%" });
											// preserve autoserch
											bindEv.call($t, $select[0], soptions);
											jgrid.fullBoolFeedback.call($t, soptions.selectFilled, "jqGridSelectFilled", {
												elem: $select[0],
												options: soptions,
												cm: cm,
												cmName: cm.name,
												iCol: ci,
												mode: mode
											});
											if (o.autosearch === true) {
												$select.change(function () {
													triggerToolbar();
													return false;
												});
											}
										}
									}, jgrid.ajaxOptions, p.ajaxSelectOptions || {}));
								} else {
									var oSv, sep, delim;
									if (searchoptions) {
										oSv = searchoptions.value === undefined ? "" : searchoptions.value;
										sep = searchoptions.separator === undefined ? ":" : searchoptions.separator;
										delim = searchoptions.delimiter === undefined ? ";" : searchoptions.delimiter;
									} else if (editoptions) {
										oSv = editoptions.value === undefined ? "" : editoptions.value;
										sep = editoptions.separator === undefined ? ":" : editoptions.separator;
										delim = editoptions.delimiter === undefined ? ";" : editoptions.delimiter;
									}
									if (oSv) {
										var elem = document.createElement("select");
										elem.style.width = "100%";
										$(elem).attr({ name: cm.index || cm.name, id: "gs_" + cm.name, role: "listbox" });
										var sv, ov, key, k;
										if (typeof oSv === "string") {
											so = oSv.split(delim);
											for (k = 0; k < so.length; k++) {
												sv = so[k].split(sep);
												ov = document.createElement("option");
												ov.value = sv[0];
												ov.innerHTML = sv[1];
												elem.appendChild(ov);
											}
										} else if (typeof oSv === "object") {
											for (key in oSv) {
												if (oSv.hasOwnProperty(key)) {
													ov = document.createElement("option");
													ov.value = key;
													ov.innerHTML = oSv[key];
													elem.appendChild(ov);
												}
											}
										}
										if (soptions.defaultValue !== undefined) { $(elem).val(soptions.defaultValue); }
										if (soptions.attr) { $(elem).attr(soptions.attr); }
										$(elem).addClass(dataFieldClass);
										$(thd).append(stbl);
										bindEv.call($t, elem, soptions);
										$("td", stbl).eq(1).append(elem);
										jgrid.fullBoolFeedback.call($t, soptions.selectFilled, "jqGridSelectFilled", {
											elem: elem,
											options: searchoptions || editoptions || {},
											cm: cm,
											cmName: cm.name,
											iCol: ci,
											mode: mode
										});
										if (o.autosearch === true) {
											$(elem).change(function () {
												triggerToolbar();
												return false;
											});
										}
									}
								}
								break;
							case "text":
								var df = soptions.defaultValue !== undefined ? soptions.defaultValue : "";

								$("td", stbl).eq(1).append("<input type='text' role='textbox' class='" + dataFieldClass + "' style='width:100%;padding:0;' name='" + (cm.index || cm.name) + "' id='gs_" + cm.name + "' value='" + df + "'/>");
								$(thd).append(stbl);

								if (soptions.attr) { $("input", thd).attr(soptions.attr); }
								bindEv.call($t, $("input", thd)[0], soptions);
								if (o.autosearch === true) {
									if (o.searchOnEnter) {
										$("input", thd).keypress(function (e) {
											var key1 = e.charCode || e.keyCode || 0;
											if (key1 === 13) {
												triggerToolbar();
												return false;
											}
											return this;
										});
									} else {
										$("input", thd).keydown(function (e) {
											var key1 = e.which;
											switch (key1) {
												case 13:
													return false;
												case 9:
												case 16:
												case 37:
												case 38:
												case 39:
												case 40:
												case 27:
													break;
												default:
													if (timeoutHnd) { clearTimeout(timeoutHnd); }
													timeoutHnd = setTimeout(function () { triggerToolbar(); }, o.autosearchDelay);
											}
										});
									}
								}
								break;
							case "custom":
								$("td", stbl).eq(1).append("<span style='width:95%;padding:0;' class='" + dataFieldClass + "' name='" + (cm.index || cm.name) + "' id='gs_" + cm.name + "'/>");
								$(thd).append(stbl);
								try {
									if ($.isFunction(soptions.custom_element)) {
										var celm = soptions.custom_element.call($t, soptions.defaultValue !== undefined ? soptions.defaultValue : "", soptions);
										if (celm) {
											celm = $(celm).addClass("customelement");
											$(thd).find("span[name='" + (cm.index || cm.name) + "']").append(celm);
										} else {
											throw "e2";
										}
									} else {
										throw "e1";
									}
								} catch (e) {
									if (e === "e1") {
										infoDialog.call($t, errcap, "function 'custom_element' " + editMsg.nodefined, bClose);
									}
									if (e === "e2") {
										infoDialog.call($t, errcap, "function 'custom_element' " + editMsg.novalue, bClose);
									} else {
										infoDialog.call($t, errcap, typeof e === "string" ? e : e.message, bClose);
									}
								}
								break;
						}
					}
					$(th).append(thd);
					$(tr).append(th);
					if (!o.searchOperators) {
						$("td", stbl).eq(0).hide();
					}
				});
				$(grid.hDiv).find(">div>.ui-jqgrid-htable>thead").append(tr);
				if (o.searchOperators) {
					$(".soptclass", tr).click(function (e) {
						var offset = $(this).offset(),
							left = (offset.left),
							top = (offset.top);
						buildRuleMenu(this, left, top);
						e.stopPropagation();
					});
					$("body").on("click", function (e) {
						if (e.target.className !== "soptclass") {
							$("#sopt_menu").hide();
						}
					});
				}
				$(".clearsearchclass", tr).click(function () {
					var ptr = $(this).parents("tr").filter(":first"),
						coli = parseInt($("td.ui-search-oper", ptr).data("colindex"), 10),
						sval = $.extend({}, colModel[coli].searchoptions || {}),
						dval = sval.defaultValue || "";
					if (colModel[coli].stype === "select") {
						if (dval) {
							$("td.ui-search-input select", ptr).val(dval);
						} else {
							$("td.ui-search-input select", ptr)[0].selectedIndex = 0;
						}
					} else {
						$("td.ui-search-input input", ptr).val(dval);
					}
					// ToDo custom search type
					if (o.autosearch === true) {
						triggerToolbar();
					}

				});
				$t.ftoolbar = true;
				$t.triggerToolbar = triggerToolbar;
				$t.clearToolbar = clearToolbar;
				$t.toggleToolbar = toggleToolbar;
			});
		},
		destroyFilterToolbar: function () {
			return this.each(function () {
				var self = this;
				if (!self.ftoolbar) {
					return;
				}
				self.triggerToolbar = null;
				self.clearToolbar = null;
				self.toggleToolbar = null;
				self.ftoolbar = false;
				$(self.grid.hDiv).find("table thead tr.ui-search-toolbar").remove();
			});
		},
		destroyGroupHeader: function (nullHeader) {
			if (nullHeader === undefined) {
				nullHeader = true;
			}
			return this.each(function () {
				var $t = this, i, l, $th, $resizing, grid = $t.grid, cm = $t.p.colModel, hc,
					thead = $("table.ui-jqgrid-htable thead", grid.hDiv);
				if (!grid) { return; }

				$($t).unbind(".setGroupHeaders");
				var $tr = $("<tr>", { role: "row" }).addClass("ui-jqgrid-labels");
				var headers = grid.headers;
				for (i = 0, l = headers.length; i < l; i++) {
					hc = cm[i].hidden ? "none" : "";
					$th = $(headers[i].el)
						.width(headers[i].width)
						.css("display", hc);
					try {
						$th.removeAttr("rowSpan");
					} catch (rs) {
						//IE 6/7
						$th.attr("rowSpan", 1);
					}
					$tr.append($th);
					$resizing = $th.children("span.ui-jqgrid-resize");
					if ($resizing.length > 0) {// resizable column
						$resizing[0].style.height = "";
					}
					$th.children("div")[0].style.top = "";
				}
				$(thead).children("tr.ui-jqgrid-labels").remove();
				$(thead).prepend($tr);

				if (nullHeader === true) {
					$($t).jqGrid("setGridParam", { "groupHeader": null });
				}
			});
		},
		setGroupHeaders: function (o) {
			o = $.extend({
				useColSpanStyle: false,
				applyLabelClasses: true,
				groupHeaders: []
			}, o || {});
			return this.each(function () {
				this.p.groupHeader = o;
				var ts = this, i, cmi, skip = 0, $tr, $colHeader, th, $th, thStyle, iCol, cghi, numberOfColumns, titleText, cVisibleColumns,
					colModel = ts.p.colModel, cml = colModel.length, ths = ts.grid.headers, $theadInTable, thClasses,
					$htable = $("table.ui-jqgrid-htable", ts.grid.hDiv), isCellClassHidden = jgrid.isCellClassHidden,
					$trLabels = $htable.children("thead").children("tr.ui-jqgrid-labels"),
					$trLastWithLabels = $trLabels.last().addClass("jqg-second-row-header"),
					$thead = $htable.children("thead"),
					$firstHeaderRow = $htable.find(".jqg-first-row-header");
				if ($firstHeaderRow[0] === undefined) {
					$firstHeaderRow = $("<tr>", { role: "row", "aria-hidden": "true" }).addClass("jqg-first-row-header").css("height", "auto");
				} else {
					$firstHeaderRow.empty();
				}
				var inColumnHeader = function (text, columnHeaders) {
					var length = columnHeaders.length, j;
					for (j = 0; j < length; j++) {
						if (columnHeaders[j].startColumnName === text) {
							return j;
						}
					}
					return -1;
				};

				$(ts).prepend($thead);
				$tr = $("<tr>", { role: "row" }).addClass("ui-jqgrid-labels jqg-third-row-header");
				for (i = 0; i < cml; i++) {
					th = ths[i].el;
					$th = $(th);
					cmi = colModel[i];
					// build the next cell for the first header row
					// ??? cmi.hidden || isCellClassHidden(cmi.classes) || $th.is(":hidden")
					thStyle = { height: "0", width: ths[i].width + "px", display: (cmi.hidden ? "none" : "") };
					$("<th>", { role: "gridcell" }).css(thStyle).addClass("ui-first-th-" + ts.p.direction + (o.applyLabelClasses ? " " + (cmi.labelClasses || "") : "")).appendTo($firstHeaderRow);

					th.style.width = ""; // remove unneeded style
					thClasses = getGuiStyles.call(ts, "colHeaders", "ui-th-column-header ui-th-" + ts.p.direction + " " + (o.applyLabelClasses ? cmi.labelClasses || "" : ""));
					iCol = inColumnHeader(cmi.name, o.groupHeaders);
					if (iCol >= 0) {
						cghi = o.groupHeaders[iCol];
						numberOfColumns = cghi.numberOfColumns;
						titleText = cghi.titleText;

						// caclulate the number of visible columns from the next numberOfColumns columns
						for (cVisibleColumns = 0, iCol = 0; iCol < numberOfColumns && (i + iCol < cml); iCol++) {
							if (!colModel[i + iCol].hidden && !isCellClassHidden(colModel[i + iCol].classes) && !$(ths[i + iCol].el).is(":hidden")) {
								cVisibleColumns++;
							}
						}

						// The next numberOfColumns headers will be moved in the next row
						// in the current row will be placed the new column header with the titleText.
						// The text will be over the cVisibleColumns columns
						$colHeader = $("<th>").attr({ role: "columnheader" })
							.addClass(thClasses)
							.css({ "height": "22px", "border-top": "0 none" })
							.html(titleText);
						if (cVisibleColumns > 0) {
							$colHeader.attr("colspan", String(cVisibleColumns));
						}
						if (ts.p.headertitles) {
							$colHeader.attr("title", $colHeader.text());
						}
						// hide if not a visible cols
						if (cVisibleColumns === 0) {
							$colHeader.hide();
						}

						$th.before($colHeader); // insert new column header before the current
						$tr.append(th);         // move the current header in the next row

						// set the counter of headers which will be moved in the next row
						skip = numberOfColumns - 1;
					} else {
						if (skip === 0) {
							if (o.useColSpanStyle) {
								// expand the header height to two rows
								$th.attr("rowspan", $trLabels.length + 1);
							} else {
								$("<th>", { role: "columnheader" })
									.addClass(thClasses)
									.css({ "display": cmi.hidden ? "none" : "", "border-top": "0 none" })
									.insertBefore($th);
								$tr.append(th);
							}
						} else {
							// move the header to the next row
							$tr.append(th);
							skip--;
						}
					}
				}
				$theadInTable = $(ts).children("thead");
				$theadInTable.prepend($firstHeaderRow);
				$tr.insertAfter($trLastWithLabels);
				$htable.append($theadInTable);

				if (o.useColSpanStyle) {
					// Increase the height of resizing span of visible headers
					$htable.find("span.ui-jqgrid-resize").each(function () {
						var $parent = $(this).parent();
						if ($parent.is(":visible")) {
							this.style.cssText = "height: " + $parent.height() + "px !important; cursor: col-resize;";
						}
					});

					// Set position of the sortable div (the main lable)
					// with the column header text to the middle of the cell.
					// One should not do this for hidden headers.
					$htable.find(".ui-th-column>div").each(function () {
						var $ts = $(this), $parent = $ts.parent();
						if ($parent.is(":visible") && $parent.is(":has(span.ui-jqgrid-resize)")) {
							// !!! it seems be wrong now
							$ts.css("top", ($parent.height() - $ts.outerHeight(true)) / 2 + "px");
						}
					});
				}
				$(ts).triggerHandler("jqGridAfterSetGroupHeaders");
			});
		},
		setFrozenColumns: function () {
			return this.each(function () {
				var $t = this, $self = $($t), p = $t.p, grid = $t.grid;
				if (!grid || p == null || p.frozenColumns === true) { return; }
				var cm = p.colModel, i, len = cm.length, maxfrozen = -1, frozen = false, frozenIds = [], $colHeaderRow,// nonFrozenIds = [],
					tid = jqID(p.id), // one can use p.idSel and remove "#"
					hoverClasses = getGuiStyles.call($t, "states.hover"),
					disabledClass = getGuiStyles.call($t, "states.disabled");
				// TODO treeGrid and grouping  Support
				// TODO: allow to edit columns AFTER frozen columns
				if (p.subGrid === true || p.treeGrid === true || p.scroll) {
					return;
				}

				// get the max index of frozen col
				for (i = 0; i < len; i++) {
					// from left, no breaking frozen
					if (cm[i].frozen !== true) {
						break;
						//nonFrozenIds.push("#jqgh_" + tid + "_" + jqID(cm[i].name));
					}
					frozen = true;
					maxfrozen = i;
					frozenIds.push("#jqgh_" + tid + "_" + jqID(cm[i].name));
				}
				if (p.sortable) {
					$colHeaderRow = $(grid.hDiv).find(".ui-jqgrid-htable .ui-jqgrid-labels");
					$colHeaderRow.sortable("destroy");
					$self.jqGrid("setGridParam", {sortable: {options: {
						items: frozenIds.length > 0 ?
								">th:not(:has(" + frozenIds.join(",") + "),:hidden)" :
								">th:not(:hidden)"
					}}});
					$self.jqGrid("sortableColumns", $colHeaderRow);
				}
				if (maxfrozen >= 0 && frozen) {
					var top = p.caption ? $(grid.cDiv).outerHeight() : 0,
						hth = $(".ui-jqgrid-htable", p.gView).height();
					//headers
					if (p.toppager) {
						top = top + $(grid.topDiv).outerHeight();
					}
					if (p.toolbar[0] === true) {
						if (p.toolbar[1] !== "bottom") {
							top = top + $(grid.uDiv).outerHeight();
						}
					}
					grid.fhDiv = $('<div style="position:absolute;left:0;top:' + top + 'px;height:' + hth + 'px;" class="' + getGuiStyles.call($t, "hDiv", "frozen-div ui-jqgrid-hdiv") + '"></div>');
					grid.fbDiv = $('<div style="position:absolute;left:0;top:' + (parseInt(top, 10) + parseInt(hth, 10) + 1) + 'px;overflow:hidden" class="frozen-bdiv ui-jqgrid-bdiv"></div>');
					$(p.gView).append(grid.fhDiv);
					var htbl = $(".ui-jqgrid-htable", p.gView).clone(true);
					/*if ($t.ftoolbar) {
						var $fixedSearchingFields = htbl.find(">thead>tr.ui-search-toolbar>th").filter(function (index) { return index <= maxfrozen; } );
						// remove tabindex from the filter toolbar
						$fixedSearchingFields.find("input,select,textarea").attr("tabindex","-1");
					}*/
					// groupheader support - only if useColSpanstyle is false
					if (p.groupHeader) {
						// TODO: remove all th which corresponds non-frozen columns. One can identify there by id
						// for example. Consider to use name attribute of th on column headers. It simplifies
						// identifying of the columns.
						$("tr.jqg-first-row-header", htbl).each(function () {
							$("th:gt(" + maxfrozen + ")", this).remove();
						});
						$("tr.jqg-third-row-header", htbl).each(function () {
							$(this).children("th[id]")
								.each(function () {
									var id = $(this).attr("id"), colName;
									if (id && id.substr(0, $t.id.length + 1) === $t.id + "_") {
										colName = id.substr($t.id.length + 1);
										if (p.iColByName[colName] > maxfrozen) {
											$(this).remove();
										}
									}
								});
							//$("th:gt(" + maxfrozen + ")", this).remove();
						});
						var swapfroz = -1, fdel = -1, cs, rs;
						// TODO: Fix processing of hidden columns 
						$("tr.jqg-second-row-header th", htbl).each(function () {
							cs = parseInt($(this).attr("colspan") || 1, 10);
							rs = parseInt($(this).attr("rowspan") || 1, 10);
							if (rs > 1) {
								swapfroz++;
								fdel++;
							} else if (cs) {
								swapfroz = swapfroz + cs;
								fdel++;
							}
							if (swapfroz === maxfrozen) {
								return false;
							}
						});
						if (swapfroz !== maxfrozen) {
							fdel = maxfrozen;
						}
						$("tr.jqg-second-row-header", htbl).each(function () {
							$("th:gt(" + fdel + ")", this).remove();
						});
					} else {
						$("tr", htbl).each(function () {
							$("th:gt(" + maxfrozen + ")", this).remove();
						});
					}
					// htable, bdiv and ftable uses table-layout:fixed; style
					// to make it working one have to set ANY width value on table.
					// The value of the width will be ignored, the sum of widths
					// of the first column will be used as the width of tables
					// and all columns will have the same width like the first row.
					// We set below just width=1 of the tables.
					$(htbl).width(1);
					// resizing stuff
					$(grid.fhDiv).append(htbl)
						.mousemove(function (e) {
							if (grid.resizing) { grid.dragMove(e); return false; }
						})
						.scroll(function () {
							// the fhDiv can be scrolled because of tab keyboard navigation
							// we prevent horizontal scrolling of fhDiv
							this.scrollLeft = 0;
						});
					if (p.footerrow) {
						var hbd = $(".ui-jqgrid-bdiv", p.gView).height();

						grid.fsDiv = $('<div style="position:absolute;left:0;top:' + (parseInt(top, 10) + parseInt(hth, 10) + parseInt(hbd, 10) + 1) + 'px;" class="frozen-sdiv ui-jqgrid-sdiv"></div>');
						$(p.gView).append(grid.fsDiv);
						var ftbl = $(".ui-jqgrid-ftable", p.gView).clone(true);
						$("tr", ftbl).each(function () {
							$("td:gt(" + maxfrozen + ")", this).remove();
						});
						$(ftbl).width(1);
						$(grid.fsDiv).append(ftbl);
					}
					// sorting stuff
					$self.bind("jqGridSortCol.setFrozenColumns", function (e, index, idxcol) {
						var previousSelectedTh = $("tr.ui-jqgrid-labels:last th:eq(" + p.lastsort + ")", grid.fhDiv), newSelectedTh = $("tr.ui-jqgrid-labels:last th:eq(" + idxcol + ")", grid.fhDiv);

						$("span.ui-grid-ico-sort", previousSelectedTh).addClass(disabledClass);
						$(previousSelectedTh).attr("aria-selected", "false");
						$("span.ui-icon-" + p.sortorder, newSelectedTh).removeClass(disabledClass);
						$(newSelectedTh).attr("aria-selected", "true");
						if (!p.viewsortcols[0]) {
							if (p.lastsort !== idxcol) {
								$("span.s-ico", previousSelectedTh).hide();
								$("span.s-ico", newSelectedTh).show();
							}
						}
					});

					// data stuff
					//TODO support for setRowData
					$(p.gView).append(grid.fbDiv);
					$(grid.bDiv).scroll(function () {
						$(grid.fbDiv).scrollTop($(this).scrollTop());
					});
					if (p.hoverrows === true) {
						$(p.idSel).unbind("mouseover").unbind("mouseout");
					}
					var safeHeightSet = function ($elem, newHeight) {
							var height = $elem.height();
							if (Math.abs(height - newHeight) >= 1) {
								$elem.height(newHeight);
								height = $elem.height();
								if (Math.abs(newHeight - height) >= 1) {
									$elem.height(newHeight + Math.round((newHeight - height)));
								}
							}
						},
						safeWidthSet = function ($elem, newWidth) {
							var width = $elem.width();
							if (Math.abs(width - newWidth) >= 1) {
								$elem.width(newWidth);
								width = $elem.width();
								if (Math.abs(newWidth - width) >= 1) {
									$elem.width(newWidth + Math.round((newWidth - width)));
								}
							}
						},
						fixDiv = function ($hDiv, hDivBase) {
							var iRow, n, $frozenRows, $rows, $row, $frozenRow, posFrozenTop, height, newHeightFrozen,
								posTop = $(hDivBase).position().top, frozenTableTop, tableTop;
							if ($hDiv != null && $hDiv.length > 0) {
								$hDiv.css({
									top: posTop,
									left: p.direction === "rtl" ? hDivBase.clientWidth - grid.fhDiv.width() : 0
								});
								$frozenRows = $hDiv.children("table").children("tbody,thead").children("tr");
								$rows = $(hDivBase).children("div").children("table").children("tbody,thead").children("tr");
								n = Math.min($frozenRows.length, $rows.length);
								frozenTableTop = n > 0 ? $($frozenRows[0]).position().top : 0;
								tableTop = n > 0 ? $($rows[0]).position().top : 0; // typically 0
								for (iRow = 0; iRow < n; iRow++) {
									$row = $($rows[iRow]);
									posTop = $row.position().top;
									$frozenRow = $($frozenRows[iRow]);
									posFrozenTop = $frozenRow.position().top;
									height = $row.height();
									if (p.groupHeader != null && p.groupHeader.useColSpanStyle && height === 0) {
										height = 0;
										for (i = 0; i < maxfrozen; i++) { // maxfrozen
											if ($row[0].cells[i].nodeName.toUpperCase() === "TH") {
												height = Math.max(height, $($row[0].cells[i]).height());
											}
										}
									}
									newHeightFrozen = height + (posTop - tableTop) + (frozenTableTop - posFrozenTop);
									safeHeightSet($frozenRow, newHeightFrozen);
								}
								safeHeightSet($hDiv, hDivBase.clientHeight);
							}
						};
					$self.bind("jqGridAfterGridComplete.setFrozenColumns", function () {
						$(p.idSel + "_frozen").remove();
						$(grid.fbDiv).height(grid.hDiv.clientHeight);
						// clone with data and events !!!
						var $frozenBTable = $(this).clone(true),
							frozenRows = $frozenBTable[0].rows,
							rows = $self[0].rows;
						$(frozenRows).filter("tr[role=row]").each(function () {
							$(this.cells).filter("td[role=gridcell]:gt(" + maxfrozen + ")").remove();
						});
						grid.fbRows = frozenRows;

						$frozenBTable.width(1).attr("id", p.id + "_frozen");
						$frozenBTable.appendTo(grid.fbDiv);
						if (p.hoverrows === true) {
							var hoverRows = function (tr, method, additionalRows) {
									$(tr)[method](hoverClasses);
									$(additionalRows[tr.rowIndex])[method](hoverClasses);
								};
							$(frozenRows).filter(".jqgrow").hover(
								function () {
									hoverRows(this, "addClass", rows);
								},
								function () {
									hoverRows(this, "removeClass", rows);
								}
							);
							$(rows).filter(".jqgrow").hover(
								function () {
									hoverRows(this, "addClass", frozenRows);
								},
								function () {
									hoverRows(this, "removeClass", frozenRows);
								}
							);
						}
						fixDiv(grid.fhDiv, grid.hDiv);
						fixDiv(grid.fbDiv, grid.bDiv);
						if (grid.sDiv) { fixDiv(grid.fsDiv, grid.sDiv); }
					});
					var myResize = function () {
							$(grid.fbDiv).scrollTop($(grid.bDiv).scrollTop());
							// TODO: the width of all column headers can be changed
							// so one should recalculate frozenWidth in other way.
							fixDiv(grid.fhDiv, grid.hDiv);
							fixDiv(grid.fbDiv, grid.bDiv);
							if (grid.sDiv) { fixDiv(grid.fsDiv, grid.sDiv); }
							var frozenWidth = grid.fhDiv[0].clientWidth;
							if (grid.fhDiv != null && grid.fhDiv.length >= 1) {
								safeHeightSet($(grid.fhDiv), grid.hDiv.clientHeight);
							}
							if (grid.fbDiv != null && grid.fbDiv.length > 0) {
								safeWidthSet($(grid.fbDiv), frozenWidth);
							}
							if (grid.fsDiv != null && grid.fsDiv.length >= 0) {
								safeWidthSet($(grid.fsDiv), frozenWidth);
							}
						};
					$(p.gBox).bind("resizestop.setFrozenColumns", function () {
						setTimeout(function () {
							myResize();
						}, 50);
					});
					$self.bind("jqGridInlineEditRow.setFrozenColumns jqGridAfterEditCell.setFrozenColumns jqGridAfterRestoreCell.setFrozenColumns jqGridInlineAfterRestoreRow.setFrozenColumns jqGridAfterSaveCell.setFrozenColumns jqGridInlineAfterSaveRow.setFrozenColumns jqGridResetFrozenHeights.setFrozenColumns jqGridGroupingClickGroup.setFrozenColumns jqGridResizeStop.setFrozenColumns", myResize);
					if (!grid.hDiv.loading) {
						$self.triggerHandler("jqGridAfterGridComplete");
					}
					p.frozenColumns = true;
				}
			});
		},
		destroyFrozenColumns: function () {
			return this.each(function () {
				var $t = this, $self = $($t), grid = $t.grid, p = $t.p, tid = jqID(p.id);
				if (!grid) { return; }
				if (p.frozenColumns === true) {
					$(grid.fhDiv).remove();
					$(grid.fbDiv).remove();
					grid.fhDiv = null;
					grid.fbDiv = null;
					grid.fbRows = null;
					if (p.footerrow) {
						$(grid.fsDiv).remove();
						grid.fsDiv = null;
					}
					$self.unbind(".setFrozenColumns");
					if (p.hoverrows === true) {
						var ptr, hoverClasses = getGuiStyles.call($t, "states.hover");
						$self.bind("mouseover", function (e) {
							ptr = $(e.target).closest("tr.jqgrow");
							if ($(ptr).attr("class") !== "ui-subgrid") {
								$(ptr).addClass(hoverClasses);
							}
						}).bind("mouseout", function (e) {
							ptr = $(e.target).closest("tr.jqgrow");
							$(ptr).removeClass(hoverClasses);
						});
					}
					p.frozenColumns = false;
					if (p.sortable) {
						var $colHeaderRow = $(grid.hDiv).find(".ui-jqgrid-htable .ui-jqgrid-labels");
						$colHeaderRow.sortable("destroy");
						$self.jqGrid("setGridParam", {sortable: {options: {
							items: ">th:not(:has(#jqgh_" + tid + "_cb" + ",#jqgh_" + tid + "_rn" + ",#jqgh_" + tid + "_subgrid),:hidden)"
						}}});
						$self.jqGrid("sortableColumns", $colHeaderRow);
					}
				}
			});
		}
	});
}(jQuery));
