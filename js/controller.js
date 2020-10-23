// Example data given in question text
var data = [
    ['name1', 'city1', 'some other info'],
    ['name2', 'city2', 'more info']
];

// Building the CSV from the Data two-dimensional array
// Each column is separated by ";" and new line "\n" for next row
var csvContent = '';

// todo change the forEach to a for with an index.

function dataToCSV(x) {
    'use strict';
    csvContent = "";
    console.log('dataToCSV');
    console.log(x);
    if (typeof x === 'undefined') {
        console.log('nothing to do.  Data is empty.');
        x = [
            ['Yes Yes Yes', 'city1', 'some other info'],
            ['name2', 'city2', 'more info']
        ];
    }

    for (let i = 0; i < x.length; i++) {
        let dataString;
        dataString = x[i].join(',');
        csvContent += dataString + '\n';
    }
    return csvContent;
}

data.forEach(function (infoArray, index) {
    let dataString = infoArray.join(',');
    csvContent += index < data.length ? dataString + '\n' : dataString;
});

// The download function takes a CSV string, the filename and mimeType as parameters
// Scroll/look down at the bottom of this snippet to see how download is called
var download = function (content, fileName, mimeType) {
    var a = document.createElement('a');
    mimeType = mimeType || 'application/octet-stream';

    if (navigator.msSaveBlob) { // IE10
        navigator.msSaveBlob(new Blob([content], {
            type: mimeType
        }), fileName);
    } else if (URL && 'download' in a) { //html5 A[download]
        a.href = URL.createObjectURL(new Blob([content], {
            type: mimeType
        }));
        a.setAttribute('download', fileName);
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    } else {
        location.href = 'data:application/octet-stream,' + encodeURIComponent(content); // only this mime type is supported
    }
};

function getJSON(urlToGet, fileName) {
    console.log("Get: " + urlToGet);
    document.getElementById("dbr_message").innerHTML = 'The file is being created.  Please wait.';
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState === 4 && this.status === 200) {
//            document.getElementById("dbr_message").innerHTML = this.responseText;
            document.getElementById("dbr_message").innerHTML = 'Downloading. Please be patient.';

            data = this.responseText;
            parsed = JSON.parse(data);
            csvContent = dataToCSV(parsed);
            document.getElementById("dbr_message").innerHTML = 'Encoding CSV. Just a second more.';
            download(csvContent, fileName, 'text/csv;encoding:utf-8');
            document.getElementById("dbr_message").innerHTML = 'Download complete.';
        }
    };
    xhttp.open("GET", urlToGet, true);
    xhttp.send();
}

// download(csvContent, 'dowload.csv', 'text/csv;encoding:utf-8');