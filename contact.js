// Script pour le menu hamburger et les fonctionnalités interactives

document.addEventListener('DOMContentLoaded', function() {
    // Gestion du menu hamburger
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    const body = document.body;

    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function() {
            // Toggle des classes actives
            hamburger.classList.toggle('active');
            navLinks.classList.toggle('active');
            
            // Empêcher le scroll quand le menu est ouvert
            if (navLinks.classList.contains('active')) {
                body.style.overflow = 'hidden';
            } else {
                body.style.overflow = 'auto';
            }
        });

        // Fermer le menu quand on clique sur un lien
        const navLinksItems = navLinks.querySelectorAll('a');
        navLinksItems.forEach(link => {
            link.addEventListener('click', function() {
                hamburger.classList.remove('active');
                navLinks.classList.remove('active');
                body.style.overflow = 'auto';
            });
        });

        // Fermer le menu quand on clique en dehors
        document.addEventListener('click', function(event) {
            if (!hamburger.contains(event.target) && !navLinks.contains(event.target)) {
                hamburger.classList.remove('active');
                navLinks.classList.remove('active');
                body.style.overflow = 'auto';
            }
        });
    }

    // Gestion des FAQ accordéons
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        
        if (question) {
            question.addEventListener('click', function() {
                // Fermer tous les autres items
                faqItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });
                
                // Toggle l'item actuel
                item.classList.toggle('active');
            });
        }
    });

    // Gestion du dropdown dans le menu mobile
    const dropdown = document.querySelector('.dropdown');
    const dropbtn = document.querySelector('.dropbtn');
    const dropdownContent = document.querySelector('.dropdown-content');

    if (dropdown && dropbtn && dropdownContent) {
        // Sur mobile, le dropdown est toujours visible, donc pas besoin de JavaScript spécial
        // Mais on peut ajouter une animation smooth
        dropbtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Si on est sur mobile, défiler vers le dropdown
            if (window.innerWidth <= 768) {
                dropdownContent.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'nearest' 
                });
            }
        });
    }

    // Smooth scroll pour les ancres
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Effet de parallaxe léger sur la navbar
    let lastScrollTop = 0;
    const navbar = document.querySelector('nav');
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scroll vers le bas
            navbar.style.transform = 'translateY(-100%)';
        } else {
            // Scroll vers le haut
            navbar.style.transform = 'translateY(0)';
        }
        
        lastScrollTop = scrollTop;
    });

    // Animation d'apparition des éléments au scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observer les éléments à animer
    const animateElements = document.querySelectorAll('.contact-item, .faq-item, .map-container');
    animateElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
});

// Fonction pour gérer le redimensionnement de la fenêtre
window.addEventListener('resize', function() {
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    const body = document.body;

    // Si on redimensionne vers desktop, fermer le menu mobile
    if (window.innerWidth > 768) {
        if (hamburger && navLinks) {
            hamburger.classList.remove('active');
            navLinks.classList.remove('active');
            body.style.overflow = 'auto';
        }
    }
});

// Fonction pour améliorer l'accessibilité
function improveAccessibility() {
    // Ajouter des attributs ARIA
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    
    if (hamburger && navLinks) {
        hamburger.setAttribute('aria-label', 'Toggle navigation menu');
        hamburger.setAttribute('aria-expanded', 'false');
        navLinks.setAttribute('aria-hidden', 'true');
    }

    // Gérer les attributs ARIA lors du toggle
    const originalToggle = hamburger.onclick;
    hamburger.addEventListener('click', function() {
        const isExpanded = hamburger.getAttribute('aria-expanded') === 'true';
        hamburger.setAttribute('aria-expanded', !isExpanded);
        navLinks.setAttribute('aria-hidden', isExpanded);
    });

    // Ajouter la navigation au clavier pour les FAQ
    const faqQuestions = document.querySelectorAll('.faq-question');
    faqQuestions.forEach(question => {
        question.setAttribute('tabindex', '0');
        question.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                question.click();
            }
        });
    });
}

// Initialiser l'accessibilité
document.addEventListener('DOMContentLoaded', improveAccessibility);