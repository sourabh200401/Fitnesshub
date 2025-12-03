// Sticky Nav
window.addEventListener('scroll', () => {
    const nav = document.getElementById('navbar');
    if (window.scrollY > 50) nav.classList.add('scrolled');
    else nav.classList.remove('scrolled');
});

// Hamburger
const hamburger = document.getElementById('hamburger');
const navRight = document.getElementById('nav-right');
const navLinks = document.querySelectorAll('.nav-link');

hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('active');
    navRight.classList.toggle('active');
});
navLinks.forEach(link => {
    link.addEventListener('click', () => {
        hamburger.classList.remove('active');
        navRight.classList.remove('active');
    });
});

// Scroll reveal
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) entry.target.classList.add('active');
    });
}, { threshold: 0.15 });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

const API_BASE = 'api.php';

// App logic
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('workout-form');
    const list = document.getElementById('workout-list');
    const filter = document.getElementById('filter-type');
    const sort = document.getElementById('sort-by');

    async function fetchWorkouts() {
        try {
            const res = await fetch(`${API_BASE}?action=list`);
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Failed to load');
            return data.workouts || [];
        } catch (err) {
            console.error(err);
            return [];
        }
    }

    async function addWorkoutToServer(payload) {
        const res = await fetch(`${API_BASE}?action=add`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        return await res.json();
    }

    async function deleteWorkoutFromServer(id) {
        const res = await fetch(`${API_BASE}?action=delete&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
        return await res.json();
    }

    let workouts = [];

    function render(currentList) {
        list.innerHTML = '';
        let filtered = (filter.value && filter.value !== 'all')
            ? currentList.filter(w => w.type === filter.value)
            : currentList.slice();

        if (sort.value === 'duration') filtered.sort((a, b) => a.duration - b.duration);
        else if (sort.value === 'intensity') {
            const m = { 'Low': 1, 'Medium': 2, 'High': 3 };
            filtered.sort((a, b) => m[a.intensity] - m[b.intensity]);
        }

        if (filtered.length === 0) {
            list.innerHTML = '<li style="color:#555; text-align:center; padding: 20px;">No workouts found.</li>';
            return;
        }

        filtered.forEach(w => {
            const color = w.intensity === 'High' ? '#e7496e'
                : w.intensity === 'Medium' ? '#33fc01'
                : '#3498db';

            const item = document.createElement('li');
            item.className = 'workout-card';
            item.style.borderLeft = `4px solid ${color}`;
            item.innerHTML = `
                <div><h3>${escapeHtml(w.type)}</h3>
                <span style="color:#999; font-size:0.9rem;">${w.duration} mins • ${w.intensity}</span></div>
                <button class="delete-btn">REMOVE</button>
            `;

            const btn = item.querySelector('.delete-btn');
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                const resp = await deleteWorkoutFromServer(w.id);
                if (resp.ok) {
                    workouts = workouts.filter(x => String(x.id) !== String(w.id));
                    populateFilterOptions();
                    render(workouts);
                } else {
                    alert('Failed to delete: ' + (resp.error || 'unknown'));
                }
                btn.disabled = false;
            });

            list.appendChild(item);
        });
    }

    function populateFilterOptions() {
        const types = [...new Set(workouts.map(w => w.type))];
        filter.innerHTML =
            '<option value="all">SHOW ALL</option>' +
            types.map(t => `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`).join('');
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    (async () => {
        workouts = await fetchWorkouts();
        populateFilterOptions();
        render(workouts);
    })();

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            type: document.getElementById('workout-type').value.trim(),
            duration: parseInt(document.getElementById('duration').value, 10),
            intensity: document.getElementById('intensity').value
        };

        if (!payload.type || !payload.duration) {
            alert('Please fill valid values.');
            return;
        }

        const res = await addWorkoutToServer(payload);
        if (res.ok) {
            workouts.unshift(res.workout);
            populateFilterOptions();
            render(workouts);
            form.reset();
        } else {
            alert('Failed to add: ' + (res.error || 'unknown'));
        }
    });

    filter.addEventListener('change', () => render(workouts));
    sort.addEventListener('change', () => render(workouts));
});

// ===== AI CHATBOT =====
const aiLink = document.getElementById('ai-chat-link');
const aiChat = document.getElementById('ai-chatbot');
const aiBody = document.getElementById('ai-chat-body');
const aiInput = document.getElementById('ai-chat-input');
const aiSend = document.getElementById('ai-chat-send');

aiLink.addEventListener('click', (e) => {
    e.preventDefault();
    aiChat.style.display = aiChat.style.display === 'flex' ? 'none' : 'flex';
});

function appendMsg(sender, text) {
    const msg = document.createElement('div');
    msg.className = `message ${sender}`;
    msg.textContent = text;
    aiBody.appendChild(msg);
    aiBody.scrollTop = aiBody.scrollHeight;
}

async function sendToAI() {
    const userText = aiInput.value.trim();
    if (!userText) return;

    appendMsg('user', userText);
    aiInput.value = '';
    appendMsg('bot', 'Thinking...');

    try {
        const res = await fetch('api.php?action=chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: userText })
        });
        const data = await res.json();

        const botMessages = document.querySelectorAll('.message.bot');
        if (botMessages.length > 0) botMessages[botMessages.length - 1].remove();

        appendMsg('bot', data.reply || "Sorry, I couldn't understand that.");

    } catch (error) {
        appendMsg('bot', '⚠️ Error: ' + error.message);
        console.error(error);
    }
}

aiSend.addEventListener('click', sendToAI);
aiInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendToAI();
});


// ===== SESSION CHECK (RESTORED) =====
async function checkSession() {
    try {
        const res = await fetch("api.php?action=session");
        const data = await res.json();

        if (!data.ok) {
            // Not logged in → show login buttons
            document.getElementById("authButtons").style.display = "flex";
            document.getElementById("userDropdown").style.display = "none";
            return false;
        }

        return true; // logged in

    } catch (e) {
        console.error("Session check failed:", e);
        return false;
    }
}


// ===== FETCH USER INFO (FINAL VERSION) =====
async function loadUserInfo() {

    const loggedIn = await checkSession();
    if (!loggedIn) return;

    try {
        const res = await fetch("api.php?action=get_user_info", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=get_user_info"
        });

        const data = await res.json();
        if (!data.ok) return;

        const u = data.info;

        document.getElementById("authButtons").style.display = "none";
        document.getElementById("userDropdown").style.display = "inline-block";

        document.getElementById("dd-name").textContent = u.name || "";
        document.getElementById("dd-age").textContent = u.age || "";
        document.getElementById("dd-phone").textContent = u.phone || "";
        document.getElementById("dd-height_cm").textContent = u.height_cm || "";
        document.getElementById("dd-weight_kg").textContent = u.weight_kg || "";
        document.getElementById("userNameLabel").textContent = u.name || "User";

    } catch (err) {
        console.error("User info fetch failed:", err);
    }
}

document.addEventListener("DOMContentLoaded", loadUserInfo);


document.getElementById("logoutBtn").addEventListener("click", async () => {
    const res = await fetch("api.php?action=logout");
    const data = await res.json();

    if (data.ok) {
        window.location.reload();
    }
});
