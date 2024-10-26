// js/main.js

const allowDesktop = false;

function initializePage() {
    waitForGoogle();
    startClock();
    blockContextMenu();
    // Additional initialization if needed
}

function waitForGoogle() {
    // Check if the Google Maps API is ready
    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.Geocoder === 'function') {
        geoLocation(); // Call the function once the API is ready
    } else {
        // Retry after 100 milliseconds if the API isn't ready
        setTimeout(waitForGoogle, 100);
    }
}

function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleButton = document.getElementById('toggle');
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleButton.textContent = '🙈';
    } else {
        passwordField.type = 'password';
        toggleButton.textContent = '👁️';
    }
}

function geoLocation() {
    if (!isMobileDevice() && !allowDesktop) {
        document.getElementById('location').textContent = 'Desktop device';
        document.getElementById('location').style.display = 'none';
        return;
    }

    if (!navigator.geolocation) {
        showError('Geolocation is not supported by your browser.');
        return;
    }

    navigator.permissions.query({name: 'geolocation'}).then(function (result) {
        if (result.state === 'granted' || result.state === 'prompt') {
            navigator.geolocation.getCurrentPosition(showPosition, geoError, {
                enableHighAccuracy: true, // Request the most accurate position possible
                timeout: 10000,           // Set a timeout in milliseconds
                maximumAge: 0             // Do not use cached position data
            });
        } else {
            showError('Geolocation permission denied.');
        }
    });
}

function showPosition(position) {

    const lat = position.coords.latitude;
    const lng = position.coords.longitude;
    const latlng = new google.maps.LatLng(lat, lng);

    const geocoder = new google.maps.Geocoder();
    geocoder.geocode({'location': latlng}, function (results, status) {
        if (status === 'OK') {
            if (results[0]) {
                document.getElementById('location').textContent = results[0].formatted_address;
            } else {
                showError('No results found');
            }
        } else {
            showError('Geocoder failed due to: ' + status);
        }
    });
}

function geoError(error) {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            showError('User denied the request for Geolocation.');
            break;
        case error.POSITION_UNAVAILABLE:
            showError('Location information is unavailable.');
            break;
        case error.TIMEOUT:
            showError('The request to get user location timed out.');
            break;
        case error.UNKNOWN_ERROR:
            showError('An unknown error occurred.');
            break;
    }
}

var errorBack = false;

window.onclick = function (event) {
    var errorFrame = document.getElementById('errorFrame');
    var errorClose = document.getElementById('errorClose');

    if ((errorFrame && errorFrame.contains(event.target)) || (errorClose && errorClose.contains(event.target))) {
        if (errorBack) {
            errorFrame.onclick = null;
            errorBack = false;
            window.history.go(-1);
        } else {
            closeError();
        }
    } else {
    }
};

function showError(text, back = false) {
    if(sessionStorage.getItem('last_error') !== text || sessionStorage.getItem('same_error') < 3) {
        if(sessionStorage.getItem('last_error') === text)
            sessionStorage.setItem('same_error', (Number(sessionStorage.getItem('same_error')) + 1));
        else {
            sessionStorage.setItem('last_error', text);
            sessionStorage.setItem('same_error', 1);
        }

        document.getElementById('errorText').textContent = text;
        document.getElementById('errorFrame').style.display = 'block';
        errorBack = back;
    } else {
        if (back === true)
            window.history.go(-1);
    }
}

function closeError() {
    var errorFrame = document.getElementById('errorFrame');
    console.log(errorFrame);
    errorFrame.style.display = 'none';

    if (errorBack) {
        // Remove the click handler to prevent memory leaks
        errorFrame.onclick = null;
        errorBack = false;

        window.history.go(-1);
    }
}

function isMobileDevice() {
    return /Mobi|Android/i.test(navigator.userAgent);
}

function startClock() {
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('clock').textContent = `${hours}:${minutes}:${seconds}`;
    }

    updateClock();
    setInterval(updateClock, 1000);
}

function blockContextMenu() {
    document.addEventListener('contextmenu', function (event) {
        event.preventDefault();
    });
    document.addEventListener('touchstart', handleTouchStart, {passive: true});
    document.addEventListener('touchend', handleTouchEnd, {passive: false});
}

let touchStartTime;

function handleTouchStart(event) {
    touchStartTime = Date.now();
}

function handleTouchEnd(event) {
    const touchDuration = Date.now() - touchStartTime;
    if (touchDuration >= 1000) {
        event.preventDefault();
    }
}

function startShiftProcess() {
    if (!navigator.geolocation && (isMobileDevice() || (!isMobileDevice() && allowDesktop))) {
        showError('Geolocation is not supported by your browser.');
        return;
    }

    navigator.geolocation.getCurrentPosition(function (position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;


        // Send AJAX request to server to start shift
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'index.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');


        xhr.onload = function () {
            if (xhr.status === 200) {
                // Handle response from server
                if (xhr.responseText) {
                    let response;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        showError('Invalid server response: ' + xhr.responseText);
                        return;
                    }
                    if (response.success) {
                        // Shift started successfully
                        // Get the current language parameter from the URL
                        const currentLang = getUrlParameter('lang') || 'English'; // Default to English if not found
                        // Redirect to index.php with the current language
                        location.href = 'index.php?lang=' + encodeURIComponent(currentLang) + '&page=hours';
                    } else {
                        // Show error message
                        showError(response.message);
                    }
                }
            } else {
                showError('An error occurred while starting the shift.');
            }
        };

        // Prepare data to send
        const params = 'action=start_shift&lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng);
        xhr.send(params);

    }, function (error) {
        geoError(error);
    });
}

function endShiftProcess() {
    // Send AJAX request to server to start shift
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'index.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');


    xhr.onload = function () {
        if (xhr.status === 200) {
            // Handle response from server
            if (xhr.responseText) {
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (e) {
                    showError('Invalid server response: ' + xhr.responseText);
                    return;
                }
                if (response.success) {
                    // Shift ended successfully
                    // Get the current language parameter from the URL
                    const currentLang = getUrlParameter('lang') || 'English'; // Default to English if not found

                    // Redirect to index.php with the current language
                    location.href = 'index.php?lang=' + encodeURIComponent(currentLang) + '&page=hours';
                } else {
                    // Show error message
                    showError(response.message);
                }
            }
        } else {
            showError('An error occurred while ending the shift.');
        }
    };

    // Prepare data to send
    const params = 'page=hours&action=end_shift';
    xhr.send(params);
}

function getUrlParameter(name) {
    name = name.replace(/[\[\]]/g, '\\$&'); // Escape characters if necessary
    const regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)');
    const results = regex.exec(window.location.href);
    if (!results || !results[2]) return null;
    return decodeURIComponent(results[2].replace(/\+/g, ' '));
}

async function getGeoAddress(address) {
    return new Promise((resolve, reject) => {
        if (!address) {
            reject('Please enter an address');
            return;
        }

        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({'address': address}, function (results, status) {
            if (status === 'OK') {
                const location = results[0].geometry.location;
                const geoData = {
                    name: results[0].formatted_address,
                    og: address,
                    lat: location.lat(),
                    lng: location.lng()
                };
                resolve(geoData);
            } else {
                reject(`Geocoding failed: {status}`);
            }
        });
    });
}

function handleReliefChange() {
    var select = document.getElementById('on_relief');
    var dateInput = document.getElementById('relief_end_date');

    if (select.value == '1') { // If "Yes" is selected
        dateInput.style.display = ''; // Show the date input
        select.style.display = 'none'; // Hide the date input again
        if(!dateInput.value) {
            let tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);

            dateInput.value = formatDate(tomorrow);
        }
    } else {
        dateInput.style.display = 'none'; // Hide the date input
        select.style.display = ''; // Hide the date input
    }
}

function checkDate() {
    var dateInput = document.getElementById('relief_end_date');
    var select = document.getElementById('on_relief');
    var selectedDate = new Date(dateInput.value);
    var today = new Date();

    // If selected date is today or earlier
    if (selectedDate <= today) {
        select.style.display = ''; // Show the date input again
        select.value = '0'; // Set the select box back to "No"
        dateInput.style.display = 'none'; // Hide the date input again
        dateInput.value = '0'; // Set the select box back to "No"
    }
}


// Function to format date to YYYY-MM-DD
function formatDate(date) {
    let d = new Date(date),
        month = "" + (d.getMonth() + 1),
        day = "" + d.getDate(),
        year = d.getFullYear();

    if (month.length < 2) month = "0" + month;
    if (day.length < 2) day = "0" + day;

    return [year, month, day].join("-");
}

// Prevent form resubmission on back navigation
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}