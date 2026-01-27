<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header_public.php';
?>

<!-- SECCIÓN HERO -->
<section class="hero-section fade-in">
    <div class="container hero-grid">

        <!-- TEXTO -->
        <div class="hero-text">
            <h1 class="hero-title">BOGATI</h1>
            <h2 class="hero-subtitle">HELADOS CON QUESO ARTESANALES</h2>

            <p class="hero-description">
                Una tradición que nace en el corazón de la Sierra.
                Helados artesanales elaborados con ingredientes
                <strong>100% naturales</strong>, creados para sorprender tu paladar
                y convertir cada visita en una experiencia inolvidable.
            </p>

           <a href="#inicio-de-todo" class="hero-btn" id="btn-conocenos">
    Conócenos más
</a>

        </div>

        <!-- IMAGEN -->
        <div class="hero-image">
            <img src="imagenes/Bogati-logo-exterior.png" alt="Bogati Exterior">
        </div>

    </div>
</section>

<!-- SECCIÓN NOSOTROS -->
<section class="section bogati-nosotros" id="nosotros">
    <div class="container text-center">
        <h2 class="section-title">¿POR QUÉ BOGATI?</h2>
        <p class="section-subtitle">
            Somos más que helados, somos una experiencia única que combina tradición, innovación y el mejor sabor.
        </p>

        <div class="features-grid">
            <div class="feature-card fade-in">
                <div class="feature-icon"><i class="fas fa-leaf"></i></div>
                <h3>100% Natural</h3>
                <p>Ingredientes frescos y naturales sin conservantes artificiales. Cada sabor es una explosión de autenticidad.</p>
            </div>

            <div class="feature-card fade-in">
                <div class="feature-icon"><i class="fas fa-heart"></i></div>
                <h3>Hecho con Amor</h3>
                <p>Preparados artesanalmente con técnicas tradicionales que respetan el producto y el proceso.</p>
            </div>

            <div class="feature-card fade-in">
                <div class="feature-icon"><i class="fas fa-star"></i></div>
                <h3>Calidad Premium</h3>
                <p>Excelencia garantizada en cada bocado. Los mejores ingredientes seleccionados cuidadosamente.</p>
            </div>
        </div>
    </div>
           

            <div class="story-block story-highlight" id="inicio-de-todo">
                <h3>EL INICIO DE TODO</h3>
                <p>
                    El <strong>16 de octubre de 2018</strong>, Santiago y Kathy abrieron el primer local
                    en Riobamba, marcando el inicio de una empresa familiar.
                </p>
                <p>
                    En la inauguración regalaron <strong>1000 helados con crema y queso</strong>,
                    logrando que más de mil personas conocieran este postre en un solo día.
                </p>
                    <div class="inicio-imagen">
        <img src="imagenes/inicio.png" alt="Primer local Bogati">
    </div>

                <p>
                            

            <div class="story-block">
                <h3>HELADOS CON QUESO</h3>
                <p>
                    La idea siempre estuvo pensada en comidas tradicionales, aunque aún no sabían exactamente cuál.
                </p>
                <p>
                    En 2017, durante un paseo familiar en <strong>Ibarra</strong>, Belén probó este postre
                    y llamó inmediatamente a Santiago con una frase que cambiaría sus vidas:
                </p>

                <blockquote>
                    “Ya sé a qué nos vamos a dedicar: Helados con Queso”.
                </blockquote>

                <p>
                    Luego de viajar a la ciudad blanca y probar el producto en familia,
                    fue claro que habían encontrado la idea para su nuevo emprendimiento.
                </p>
            </div>

    </div>

</section>

<!-- FOOTER -->
<footer class="main-footer">
    <div class="footer-content">
        <div class="footer-info">
            <div class="footer-logo">BOGATI</div>
            <p>Helados con Queso – Buenos por fuera, buenos por dentro</p>

            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-tiktok"></i></a>
                <a href="#"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>

        <div class="footer-links">
            <h5>CONTACTO</h5>
            <p><i class="fas fa-phone"></i> 1800-BOGATI</p>
            <p><i class="fas fa-envelope"></i> info@bogati.com</p>
            <p><i class="fas fa-map-marker-alt"></i> Ecuador</p>
        </div>
    </div>

    <div class="copyright text-center mt-4">
        <p>&copy; <?= date('Y'); ?> Bogati Franquicia. Todos los derechos reservados.</p>
        <p>Proyecto Universitario</p>
    </div>
    </div>
</footer>

<script>
    // Smooth scroll para navegación interna
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Header efecto al hacer scroll
    window.addEventListener('scroll', function() {
        const header = document.querySelector('.header');
        if (window.scrollY > 50) {
            header.style.background = 'rgba(249, 227, 202, 0.95)';
            header.style.backdropFilter = 'blur(10px)';
        } else {
            header.style.background = '#f9e3ca';
            header.style.backdropFilter = 'none';
        }
    });
</script>
<script>
document.getElementById("btn-conocenos").addEventListener("click", function(e) {
    e.preventDefault();

    const seccion = document.getElementById("inicio-de-todo");
    seccion.classList.add("show");

    seccion.scrollIntoView({
        behavior: "smooth",
        block: "start"
    });
});
</script>

</body>

</html>

<?php
require_once __DIR__ . '/includes/footer.php';
?>