;(function($) {
  $(function() {
    $('#default').raty();

    $('#score').raty({ score: 3 });

    $('#score-callback').raty({
      score: function() {
        return $(this).attr('data-score');
      }
    });

    $('#scoreName').raty({ scoreName: 'entity[score]' });

    $('#number').raty({ number: 10 });

    $('#number-callback').raty({
      number: function() {
        return $(this).attr('data-number');
      }
    });

    $('#numberMax').raty({
      numberMax : 5,
      number    : 100
    });

    $('#readOnly').raty({ readOnly: true, score: 3 });

    $('#readOnly-callback').raty({
      readOnly: function() {
        return 'true becomes readOnly' == 'true becomes readOnly';
      }
    });

    $('#noRatedMsg').raty({
      readOnly   : true,
      noRatedMsg : "I'am readOnly and I haven't rated yet!"
    });

    $('#halfShow-true').raty({ score: 3.26 });

    $('#halfShow-false').raty({
      halfShow : false,
      score    : 3.26
    });

    $('#round').raty({
      round : { down: .26, full: .6, up: .76 },
      score : 3.26
    });

    $('#half').raty({ half: true });

    $('#starHalf').raty({
      half     : true,
      starHalf : 'fa fa-star-half',
    });

    $('#click').raty({
      click: function(score, evt) {
        alert('ID: ' + $(this).attr('id') + "\nscore: " + score + "\nevent: " + evt.type);
      }
    });

    $('#hints').raty({ hints: ['a', null, '', undefined, '*_*']});

    $('#star-off-and-star-on').raty({
      starOff : 'fa fa-bell-o',
      starOn  : 'fa fa-bell'
    });

    $('#cancel').raty({ cancel: true });

    $('#cancelHint').raty({
      cancel     : true,
      cancelHint : 'My cancel hint!'
    });

    $('#cancelPlace').raty({
      cancel      : true,
      cancelPlace : 'right'
    });

    $('#cancel-off-and-cancel-on').raty({
      cancel    : true,
      cancelOff : 'fa fa-minus-square-o',
      cancelOn  : 'fa fa-minus-square',
    });

    $('#iconRange').raty({
      starOff   : 'lib/images/star-off.png',
      iconRange : [
        { range: 1, on: 'fa fa-cloud', off: 'fa fa-circle-o' },
        { range: 2, on: 'fa fa-cloud-download', off: 'fa fa-circle-o' },
        { range: 3, on: 'fa fa-cloud-upload', off: 'fa fa-circle-o' },
        { range: 4, on: 'fa fa-circle', off: 'fa fa-circle-o' },
        { range: 5, on: 'fa fa-cogs', off: 'fa fa-circle-o' }
      ]
    });

    $('#size').raty({
      cancel    : true,
      half      : true,
      size      : 24
    });

    $('#width').raty({ width: 150 });

    $('#target-div').raty({
      cancel : true,
      target : '#target-div-hint'
    });

    $('#target-text').raty({
      cancel : true,
      target : '#target-text-hint'
    });

    $('#target-textarea').raty({
      cancel : true,
      target : '#target-textarea-hint'
    });

    $('#target-select').raty({
      cancel : true,
      target : '#target-select-hint'
    });

    $('#targetType').raty({
      cancel     : true,
      target     : '#targetType-hint',
      targetType : 'score'
    });

    $('#targetKeep').raty({
      cancel     : true,
      target     : '#targetKeep-hint',
      targetKeep : true
    });

    $('#targetText').raty({
      target     : '#targetText-hint',
      targetText : '--'
    });

    $('#targetFormat').raty({
      target       : '#targetFormat-hint',
      targetFormat : 'Rating: {score}'
    });

    $('#mouseover').raty({
      mouseover: function(score, evt) {
        alert('ID: ' + $(this).attr('id') + "\nscore: " + score + "\nevent: " + evt.type);
      }
    });

    $('#mouseout').raty({
      width: 150,
      mouseout: function(score, evt) {
        alert('ID: ' + $(this).attr('id') + "\nscore: " + score + "\nevent: " + evt.type);
      }
    });
  });
})(jQuery);