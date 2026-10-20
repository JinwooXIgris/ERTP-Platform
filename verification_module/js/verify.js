// sends JSON to the PHP API using Fetch
async function postJSON(url, data) {
    try {
        var response = await fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });
        return await response.json();
    } catch (error) {
        return { success: false, status: "invalid", message: "Could not reach the server." };
    }
}

// ---------- login and logout ----------
async function checkSession() {
    var response = await fetch("api/officer-login.php?action=me");
    var data = await response.json();
    if (data.success) {
        showGate(data.name);
    }
}

async function login() {
    var data = await postJSON("api/officer-login.php", {
        email: document.getElementById("email").value,
        password: document.getElementById("password").value
    });
    var box = document.getElementById("loginMsg");
    if (data.success) {
        box.className = "";
        box.textContent = "";
        showGate(data.name);
    } else {
        box.className = "msg bad";
        box.textContent = data.message;
    }
}

async function logout() {
    await fetch("api/officer-login.php?action=logout");
    document.getElementById("gate").classList.add("hidden");
    document.getElementById("loginCard").classList.remove("hidden");
}

function showGate(name) {
    document.getElementById("loginCard").classList.add("hidden");
    document.getElementById("gate").classList.remove("hidden");
    document.getElementById("officerName").textContent = "(" + name + ")";
    loadSummary();
}

// ---------- verify a ticket ----------
async function verifyTicket() {
    var code = document.getElementById("ticketCode").value;
    var data = await postJSON("api/verify-ticket.php", { code: code });

    if (data.status == "login_needed") {
        logout();
        return;
    }

    // coloured result box: green (valid), orange (used) or red (invalid)
    var box = document.getElementById("result");
    box.className = "result " + data.status;
    box.textContent = data.message;

    showDetails(data.ticket);
    loadSummary();
}

function showDetails(ticket) {
    var area = document.getElementById("details");
    area.innerHTML = "";
    if (!ticket) {
        return;
    }
    var lines = [
        "Ticket: " + ticket.ticket_number,
        "Holder: " + ticket.holder_name,
        "Category: " + ticket.category_name,
        "Status: " + ticket.status
    ];
    if (ticket.used_at) {
        lines.push("Checked in at: " + ticket.used_at);
    }
    for (var i = 0; i < lines.length; i++) {
        var div = document.createElement("div");
        div.textContent = lines[i];   // textContent keeps names safe
        area.appendChild(div);
    }
}

// ---------- summary and recent scans ----------
async function loadSummary() {
    var response = await fetch("api/verification-log.php");
    var data = await response.json();
    if (!data.success) {
        return;
    }
    var s = data.stats;

    document.getElementById("stats").innerHTML =
        "<div class='stat'><b>" + s.tickets_issued + "</b>Tickets issued</div>" +
        "<div class='stat'><b>" + s.tickets_used + "</b>Checked in</div>" +
        "<div class='stat'><b>" + s.used + "</b>Reused tickets refused</div>" +
        "<div class='stat'><b>" + s.invalid + "</b>Invalid tickets refused</div>";

    var table = document.getElementById("recentTable");
    table.innerHTML = "<tr><th>Time</th><th>Scanned</th><th>Result</th><th>Officer</th></tr>";
    for (var i = 0; i < data.recent.length; i++) {
        var r = data.recent[i];
        var row = table.insertRow();
        row.insertCell().textContent = r.created_at;
        row.insertCell().textContent = r.input_code;
        row.insertCell().textContent = r.result;
        row.insertCell().textContent = r.officer || "-";
    }
}

// press Enter to verify
document.getElementById("ticketCode").addEventListener("keydown", function (event) {
    if (event.key == "Enter") {
        verifyTicket();
    }
});

checkSession();
