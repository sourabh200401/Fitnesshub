let modal = document.getElementById("instructorModal");
let selectedTrainerId = null;

const instructorData = {
    1: {
        name: "Amit Sharma",
        img: "https://images.unsplash.com/photo-1605296867304-46d5465a13f1?auto=format&fit=crop&w=900&q=80",
        experience: "6+ Years",
        cases: 240,
        price: "₹499 / session"
    },
    2: {
        name: "Rohit Verma",
        img: "https://images.unsplash.com/photo-1599058917212-d750089bc07e?auto=format&fit=crop&w=900&q=80",
        experience: "4+ Years",
        cases: 180,
        price: "₹399 / session"
    },
    3: {
        name: "Kunal Singh",
        img: "https://images.pexels.com/photos/5327451/pexels-photo-5327451.jpeg",
        experience: "5+ Years",
        cases: 210,
        price: "₹449 / session"
    },
    4: {
        name: "Aditya Rao",
        img: "https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?auto=format&fit=crop&w=900&q=80",
        experience: "7+ Years",
        cases: 320,
        price: "₹599 / session"
    },
    5: {
        name: "Mahesh Das",
        img: "https://images.unsplash.com/photo-1558611848-73f7eb4001a1?auto=format&fit=crop&w=900&q=80",
        experience: "3+ Years",
        cases: 120,
        price: "₹349 / session"
    }
};

document.querySelectorAll(".instructor-card").forEach(card => {
    card.addEventListener("click", () => {
        let id = card.dataset.id;
        let d = instructorData[id];
        selectedTrainerId = id;

        document.getElementById("modalName").textContent = d.name;
        document.getElementById("modalImg").src = d.img;
        document.getElementById("modalExp").textContent = d.experience;
        document.getElementById("modalCases").textContent = d.cases;
        document.getElementById("modalPrice").textContent = d.price;

        modal.style.display = "flex";
    });
});

modal.addEventListener("click", e => {
    if (e.target === modal) modal.style.display = "none";
});
