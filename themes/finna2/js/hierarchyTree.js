/*global VuFind */

var hierarchyID, recordID, hierarchySource, htmlID, hierarchyContext;

/* Utility functions */
function htmlEncodeId(id) {
  return id.replace(/\W/g, "-"); // Also change Hierarchy/TreeRenderer/JSTree.php
}
function html_entity_decode(string) {
  var hash_map = {
    '&': '&amp;',
    '>': '&gt;',
    '<': '&lt;'
  };
  var tmp_str = string.toString();

  for (var symbol in hash_map) {
    if (Object.prototype.hasOwnProperty.call(hash_map, symbol)) {
      var entity = hash_map[symbol];
      tmp_str = tmp_str.split(entity).join(symbol);
    }
  }
  tmp_str = tmp_str.split('&#039;').join("'");

  return tmp_str;
}

function getRecord(id) {
  $.ajax({
    url: VuFind.path + '/Hierarchy/GetRecord?' + $.param({id: id, hierarchySource: hierarchySource}),
    dataType: 'html'
  })
    .done(function getRecordDone(response) {
      $('#tree-preview').html(VuFind.updateCspNonce(html_entity_decode(response)));
      // Remove the old path highlighting
      $('#hierarchyTree a').removeClass("jstree-highlight");
      // Add Current path highlighting
      var jsTreeNode = $(":input[value='" + id + "']").parent();
      jsTreeNode.children("a").addClass("jstree-highlight");
      jsTreeNode.parents("li").children("a").addClass("jstree-highlight");
    });
}

function changeNoResultLabel(display) {
  if (display) {
    $("#treeSearchNoResults").removeClass('hidden');
  } else {
    $("#treeSearchNoResults").addClass('hidden');
  }
}

function changeLimitReachedLabel(display) {
  if (display) {
    $("#treeSearchLimitReached").removeClass('hidden');
  } else {
    $("#treeSearchLimitReached").addClass('hidden');
  }
}

var searchAjax = false;
function doTreeSearch() {
  $('#treeSearchLoadingImg').removeClass('hidden');
  var keyword = $("#treeSearchText").val();
  if (keyword.length === 0) {
    $('#hierarchyTree').find('.jstree-search').removeClass('jstree-search');
    var tree = $('#hierarchyTree').jstree(true);
    tree.close_all();
    tree._open_to(htmlID);
    $('#treeSearchLoadingImg').addClass('hidden');
  } else {
    if (searchAjax) {
      searchAjax.abort();
    }
    searchAjax = $.ajax({
      url: VuFind.path + '/Hierarchy/SearchTree?' + $.param({
        lookfor: keyword,
        hierarchyID: hierarchyID,
        hierarchySource: hierarchySource,
        type: $("#treeSearchType").val()
      }) + "&format=true"
    })
      .done(function searchTreeAjaxDone(data) {
        if (data.results.length > 0) {
          $('#hierarchyTree').find('.jstree-search').removeClass('jstree-search');
          var jstree = $('#hierarchyTree').jstree(true);
          jstree.close_all();
          for (var i = data.results.length; i--;) {
            var id = htmlEncodeId(data.results[i]);
            jstree._open_to(id);
          }
          for (var j = data.results.length; j--;) {
            var tid = htmlEncodeId(data.results[j]);
            $('#hierarchyTree').find('#' + tid).addClass('jstree-search');
          }
          changeNoResultLabel(false);
          changeLimitReachedLabel(data.limitReached);
        } else {
          changeNoResultLabel(true);
        }
        $('#treeSearchLoadingImg').addClass('hidden');
      });
  }
}

function buildJSONNodes(xml) {
  var jsonNode = [];
  $(xml).children('item').each(function xmlTreeChildren() {
    var content = $(this).children('content');
    var id = content.children("name[class='JSTreeID']");
    var name = content.children('name[href]');
    jsonNode.push({
      id: htmlEncodeId(id.text()),
      text: name.text(),
      li_attr: { recordid: id.text() },
      a_attr: {
        href: name.attr('href'),
        title: name.text()
      },
      type: name.attr('href').match(/\/Collection\//) ? 'collection' : 'record',
      children: buildJSONNodes(this)
    });
  });
  return jsonNode;
}

function buildTreeWithXml(cb) {
  $.ajax({
    url: VuFind.path + '/Hierarchy/GetTree',
    data: {
      hierarchyID: hierarchyID,
      id: recordID,
      context: hierarchyContext,
      hierarchySource: hierarchySource,
      mode: 'Tree'
    }
  })
    .done(function getTreeDone(xml) {
      var nodes = buildJSONNodes($(xml).find('root'));
      cb.call(this, nodes);
    });
}

$(function hierarchyTreeReady() {
  // "Back to top" link
  $('#hierarchyTree').on("scroll", function onScrollHierarchyTree() {
    var modalContent = $('#hierarchyTree').scrollTop();
    if (modalContent > 1500) {
      $('#modal .back-to-up').removeClass('hidden');
    }
    else {
      $('#modal .back-to-up').addClass('hidden');
    }
  });
  $('.back-to-up').on('click', function onClickBackToUp() {
    $('#hierarchyTree, #modal').animate({scrollTop: 0 }, 200);
  });

  // Code for the search button
  hierarchyID = $("#hierarchyTree").find(".hiddenHierarchyId")[0].value;
  recordID = $("#hierarchyTree").find(".hiddenRecordId")[0].value || 'Solr';
  hierarchySource = $("#hierarchyTree").find(".hiddenRecordSource");
  hierarchySource = hierarchySource.length ? hierarchySource[0].value : 'Solr';

  htmlID = htmlEncodeId(recordID);
  hierarchyContext = $("#hierarchyTree").find(".hiddenContext")[0].value;

  $("#hierarchyLoading").removeClass('hide');

  $("#hierarchyTree")
    .on("ready.jstree", function jsTreeReady(/*event, data*/) {
      $("#hierarchyLoading").addClass('hide');
      var tree = $("#hierarchyTree").jstree(true);
      tree.select_node(htmlID);
      tree.open_node(htmlID);
      tree._open_to(htmlID);

      if (hierarchyContext === "Collection") {
        getRecord(recordID);
      }

      $('.template-dir-record .back-to-up').on('click', function onClickBackToUp() {
        $('html, body').animate({scrollTop: $('#hierarchyTreeHolder').offset().top - 70}, 200);
      });

      $("#hierarchyTree").on('select_node.jstree', function jsTreeSelect(e, resp) {
        if (hierarchyContext === "Record") {
          window.location.href = resp.node.a_attr.href;
        } else {
          getRecord(resp.node.li_attr.recordid);
        }
      });

      // Scroll to the current record only in modal
      if ($('#hierarchyTree').parents('#modal').length > 0) {
        var hTree = $('#hierarchyTree');
        var offsetTop = hTree.offset().top;
        var maxHeight = Math.max($(window).height() - 200, 200);
        var scrollTop = Math.trunc($('.jstree-clicked').offset().top - offsetTop + hTree.scrollTop() - 50);
        hTree.css('max-height', maxHeight + 'px').css('overflow', 'auto');
        hTree.animate({
          scrollTop: scrollTop
        }, 1500);
      }
      var jsTreeFirstChild = $(this).find('.jstree-container-ul').children('li').first();

      if (jsTreeFirstChild.find('ul').length === 0) {
        jsTreeFirstChild.append(
          '<div class="empty-collection">'
          + '<span class="highlight">'
          + VuFind.translate('collection_empty')
          + '</span>'
          + '</div>'
        );
      }
    })
    .jstree({
      plugins: ['search', 'types'],
      core: {
        data: function jsTreeCoreData(obj, cb) {
          $.ajax({
            url: VuFind.path + '/Hierarchy/GetTreeJSON',
            data: {
              hierarchyID: hierarchyID,
              id: recordID,
              hierarchySource: hierarchySource
            },
            statusCode: {
              200: function jsTree200Status(json /*, status, request*/) {
                cb.call(this, json);
              },
              204: function jsTree204Status(/*json, status, request*/) { // No Content
                buildTreeWithXml(cb);
              },
              503: function jsTree503Status(/*json, status, request*/) { // Service Unavailable
                buildTreeWithXml(cb);
              }
            }
          });
        }
      },
      types: {
        record: {
          icon: 'fa fa-file-o'
        },
        collection: {
          icon: 'fa fa-folder'
        }
      }
    });

  $('#treeSearch').removeClass('hidden');
  $('#treeSearch [type=submit]').on("click", doTreeSearch);
  $('#treeSearchText').on("keyup", function treeSearchKeyup(e) {
    var code = (e.keyCode ? e.keyCode : e.which);
    if (code === 13 || $(this).val().length === 0) {
      doTreeSearch();
    }
  });
});
