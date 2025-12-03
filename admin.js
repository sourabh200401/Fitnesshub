const API = "api.php";

/* ---------------- LOAD USERS ---------------- */
async function loadUsers() {
    try {
        const res = await fetch(API + "?action=admin_list_users");
        const data = await res.json();

        const tbody = document.getElementById("user-table-body");
        tbody.innerHTML = "";

        data.users.forEach(u => {
            tbody.innerHTML += `
                <tr>
                    <td>${u.id}</td>
                    <td>${u.name}</td>
                    <td>${u.phone}</td>
                    <td>${u.email}</td>
                    <td>
                        <button class="admin-delete-btn" onclick="deleteUser(${u.id})">Delete</button>
                    </td>
                </tr>
            `;
        });

        document.getElementById("total-users").textContent = data.users.length;

    } catch (err) {
        console.error("Failed loading users", err);
    }
}

/* ---------------- LOAD WORKOUTS ---------------- */
async function loadWorkouts() {
    try {
        const res = await fetch(API + "?action=admin_list_workouts");
        const data = await res.json();

        const tbody = document.getElementById("workout-table-body");
        tbody.innerHTML = "";

        data.workouts.forEach(w => {
            tbody.innerHTML += `
                <tr>
                    <td>${w.id}</td>
                    <td>${w.user_name}</td>
                    <td>${w.type}</td>
                    <td>${w.duration} mins</td>
                    <td>${w.intensity}</td>
                    <td>
                        <button class="admin-delete-btn" onclick="deleteWorkout(${w.id})">Delete</button>
                    </td>
                </tr>
            `;
        });

        document.getElementById("total-workouts").textContent = data.workouts.length;

    } catch (err) {
        console.error("Failed loading workouts", err);
    }
}

/* ---------------- DELETE USER ---------------- */
async function deleteUser(id) {
    if (!confirm("Are you sure you want to delete this user?")) return;

    const res = await fetch(API + "?action=admin_delete_user&id=" + id);
    const data = await res.json();

    if (data.ok) loadUsers();
}

/* ---------------- DELETE WORKOUT ---------------- */
async function deleteWorkout(id) {
    if (!confirm("Delete this workout?")) return;

    const res = await fetch(API + "?action=admin_delete_workout&id=" + id);
    const data = await res.json();

    if (data.ok) loadWorkouts();
}

/* Initialize */
loadUsers();
loadWorkouts();
/* ---------------- LOAD ANALYTICS ---------------- */
async function loadAnalytics() {
    const users = await fetch(API + "?action=admin_list_users").then(r=>r.json());
    const workouts = await fetch(API + "?action=admin_list_workouts").then(r=>r.json());

    /* ---------- USERS GROWTH ----------- */
    const userMonths = {};
    users.users.forEach(u => {
        const m = (u.created_at ?? "2025-01").substring(0,7);
        userMonths[m] = (userMonths[m] || 0) + 1;
    });

    new Chart(document.getElementById("chartUsers"), {
        type: "line",
        data: {
            labels: Object.keys(userMonths),
            datasets: [{
                label: "User Growth",
                data: Object.values(userMonths)
            }]
        }
    });

    /* ---------- WORKOUT INTENSITY ----------- */
    const intensity = {Low:0, Medium:0, High:0};
    workouts.workouts.forEach(w => intensity[w.intensity]++);

    new Chart(document.getElementById("chartIntensity"), {
        type: "bar",
        data: {
            labels: ["Low", "Medium", "High"],
            datasets: [{
                label: "Workouts by Intensity",
                data: [intensity.Low, intensity.Medium, intensity.High]
            }]
        }
    });

    /* ---------- DAILY WORKOUTS ----------- */
    const daily = {};
    workouts.workouts.forEach(w => {
        const d = w.created_at.substring(0,10);
        daily[d] = (daily[d] || 0) + 1;
    });

    new Chart(document.getElementById("chartDaily"), {
        type: "line",
        data: {
            labels: Object.keys(daily),
            datasets: [{
                label: "Daily Workout Count",
                data: Object.values(daily)
            }]
        }
    });
}

loadAnalytics();
