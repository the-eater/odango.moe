var script = document.querySelector('script.collect-js');
var results = document.querySelector('.results');
var query = script.dataset.query;

ajax('/part/collect?q=' + escape(query), function (html){
    results.innerHTML = html;
});
