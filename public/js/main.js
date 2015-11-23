var input = document.querySelector('.search > input');
var autocomplete = document.querySelector('.autocomplete');
var autocompleteTimeout = false;

var selected = false;
input.addEventListener('keydown', function (e) {
    // DOWN
    if (e.keyCode == 40) {
        if (selected == false) {
            selected = autocomplete.children[0];
            selected.classList.add('selected');
        } else if (selected.nextSibling) {
            selected.classList.remove('selected');
            selected = selected.nextSibling;
            selected.classList.add('selected');
        }
    // UP
    } else if (e.keyCode == 38) {
        if (selected == false) {
            selected = autocomplete.children[autocomplete.children.length - 1];
            selected.classList.add('selected');
        } else if (selected.previousSibling) {
            selected.classList.remove('selected');
            selected = selected.previousSibling;
            selected.classList.add('selected');
        }
    // ENTER
    } else if (e.keyCode == 13) {
        if (selected !== false) {
            input.value = selected.innerText;
        }
    }

});

input.addEventListener('input', function (e) {
    if (autocompleteTimeout !== false) {
        clearTimeout(autocompleteTimeout);
    }

    autocompleteTimeout = setTimeout(function (){
        if (input.value == "") {
            autocomplete.innerHTML = "";
            selected = false;
            return;
        }

        ajax('/json/v1/autocomplete?q=' + escape(input.value), function (data) {
            autocomplete.innerHTML = "";
            selected = false;
            JSON.parse(data).slice(0, 6).forEach(function (item) {
                var itemDiv = document.createElement("div");
                itemDiv.className = 'item';
                itemDiv.innerText = item;
                autocomplete.appendChild(itemDiv);
            });
        });
    }, 200);
});


function ajax(url, callback)
{
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4)  {
            callback(xhr.responseText);
        }
    }
    xhr.open('GET', url, true);
    xhr.send();
}
