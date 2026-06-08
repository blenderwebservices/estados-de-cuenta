<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>FilaConcilia - Conciliación Inteligente de Estados de Cuenta</title>
        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <!-- Tailwind Play CDN -->
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Outfit', 'sans-serif'],
                        },
                    }
                }
            }
        </script>
        <style>
            .glassmorphism {
                background: rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border: 1px solid rgba(255, 255, 255, 0.15);
            }
            .dark .glassmorphism {
                background: rgba(15, 15, 15, 0.6);
                border: 1px solid rgba(255, 255, 255, 0.05);
            }
            .glow-bg {
                filter: blur(150px);
                opacity: 0.15;
            }
        </style>
    </head>
    <body class="bg-slate-900 text-slate-100 font-sans antialiased relative overflow-x-hidden min-h-screen">
        
        <!-- Background decorative glows -->
        <div class="absolute top-[-20%] left-[-10%] w-[500px] h-[500px] rounded-full bg-blue-500 glow-bg pointer-events-none"></div>
        <div class="absolute top-[30%] right-[-10%] w-[600px] h-[600px] rounded-full bg-violet-600 glow-bg pointer-events-none"></div>
        <div class="absolute bottom-[-10%] left-[20%] w-[500px] h-[500px] rounded-full bg-emerald-500 glow-bg pointer-events-none"></div>

        <!-- Navigation Bar -->
        <header class="fixed top-0 left-0 right-0 z-50 px-6 py-4">
            <div class="max-w-7xl mx-auto flex items-center justify-between glassmorphism rounded-full px-6 py-3 shadow-lg">
                <!-- Logo -->
                <a href="#" class="flex items-center gap-2 group">
                    <div class="bg-gradient-to-tr from-blue-500 to-violet-500 p-2 rounded-lg text-white font-bold text-lg shadow-md group-hover:scale-105 transition-all">
                        FC
                    </div>
                    <span class="font-bold text-xl tracking-tight bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">FilaConcilia</span>
                </a>

                <!-- Suggested Sections Menu -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-300">
                    <a href="#features" class="hover:text-white transition-colors">Características</a>
                    <a href="#how-it-works" class="hover:text-white transition-colors">Cómo Funciona</a>
                    <a href="#banks" class="hover:text-white transition-colors">Bancos Soportados</a>
                </nav>

                <!-- Auth Special Menu -->
                <div class="flex items-center gap-3">
                    @auth
                        <a href="/admin" class="px-5 py-2 rounded-full bg-gradient-to-r from-blue-500 to-violet-600 hover:from-blue-600 hover:to-violet-700 text-white text-sm font-semibold shadow-md transition-all hover:scale-105">
                            Ir al Dashboard
                        </a>
                    @else
                        <a href="/admin/login" class="px-4 py-2 text-sm font-semibold hover:text-white text-slate-300 transition-colors">
                            Iniciar Sesión
                        </a>
                        <a href="/admin/register" class="px-5 py-2 rounded-full bg-white hover:bg-slate-200 text-slate-900 text-sm font-semibold shadow-md transition-all hover:scale-105">
                            Registrarse
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="pt-32 pb-20 px-6">
            <div class="max-w-5xl mx-auto text-center">
                <span class="inline-block px-4 py-1.5 rounded-full glassmorphism text-xs font-semibold text-blue-400 mb-6 uppercase tracking-wider">
                    ⚡ Inteligencia Artificial y Parsing de Precisión
                </span>
                <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight mb-8 leading-tight">
                    Parsea y Concilia tus Estados de Cuenta <br>
                    <span class="bg-gradient-to-r from-blue-400 via-violet-400 to-emerald-400 bg-clip-text text-transparent">
                        Bancarios en Segundos
                    </span>
                </h1>
                <p class="text-lg sm:text-xl text-slate-400 max-w-3xl mx-auto mb-10 leading-relaxed">
                    Sube tus PDFs de AMEX, BBVA, Banamex o Scotiabank. FilaConcilia detecta el formato, extrae todos los movimientos y genera planillas de conciliación en Excel con cuadre del 100% garantizado.
                </p>
                <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                    <a href="/admin" class="w-full sm:w-auto px-8 py-4 rounded-full bg-gradient-to-r from-blue-500 to-violet-600 hover:from-blue-600 hover:to-violet-700 text-white font-bold shadow-lg shadow-blue-500/20 hover:scale-105 transition-all text-center">
                        Comenzar Ahora
                    </a>
                    <a href="#how-it-works" class="w-full sm:w-auto px-8 py-4 rounded-full glassmorphism hover:bg-white/10 text-white font-bold transition-all text-center">
                        Ver Demostración
                    </a>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-20 px-6 bg-slate-900/50">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent mb-4">
                        Características Clave
                    </h2>
                    <p class="text-slate-400 max-w-xl mx-auto">
                        Diseñado para contadores y departamentos de administración que buscan optimizar tiempos de conciliación.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="p-8 rounded-2xl glassmorphism hover:border-blue-500/50 transition-all duration-300">
                        <div class="bg-blue-500/10 p-3 rounded-lg text-blue-400 w-fit mb-6">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-4">Detección Inteligente</h3>
                        <p class="text-slate-400 leading-relaxed">
                            Basta con arrastrar el archivo PDF. El motor identifica automáticamente el banco, número de cuenta y tipo de divisa en milisegundos.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="p-8 rounded-2xl glassmorphism hover:border-violet-500/50 transition-all duration-300">
                        <div class="bg-violet-500/10 p-3 rounded-lg text-violet-400 w-fit mb-6">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-4">Cuadre Matemático</h3>
                        <p class="text-slate-400 leading-relaxed">
                            Validación cruzada entre los saldos iniciales, finales y la suma total de cargos y abonos. Badges visuales de cuadre perfecto.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="p-8 rounded-2xl glassmorphism hover:border-emerald-500/50 transition-all duration-300">
                        <div class="bg-emerald-500/10 p-3 rounded-lg text-emerald-400 w-fit mb-6">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-4">Exportación Premium</h3>
                        <p class="text-slate-400 leading-relaxed">
                            Genera hojas de cálculo Excel limpias con formato nativo de fecha, importes negativos para cargos y una pestaña de auditoría.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section id="how-it-works" class="py-20 px-6">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent mb-4">
                        ¿Cómo Funciona?
                    </h2>
                    <p class="text-slate-400 max-w-xl mx-auto">
                        Una experiencia de usuario pulida y veloz en tan solo 4 pasos.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Step 1 -->
                    <div class="text-center relative">
                        <div class="bg-slate-800 border border-slate-700 w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold text-blue-400 mx-auto mb-6">
                            1
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Carga el PDF</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            Arrastra y suelta tu estado de cuenta en el cargador seguro.
                        </p>
                    </div>

                    <!-- Step 2 -->
                    <div class="text-center relative">
                        <div class="bg-slate-800 border border-slate-700 w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold text-violet-400 mx-auto mb-6">
                            2
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Extracción</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            El script de Python extrae importes, fechas y conceptos.
                        </p>
                    </div>

                    <!-- Step 3 -->
                    <div class="text-center relative">
                        <div class="bg-slate-800 border border-slate-700 w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold text-emerald-400 mx-auto mb-6">
                            3
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Conciliación</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            El sistema audita los totales contra las sumatorias internas.
                        </p>
                    </div>

                    <!-- Step 4 -->
                    <div class="text-center relative">
                        <div class="bg-slate-800 border border-slate-700 w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold text-amber-400 mx-auto mb-6">
                            4
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Descarga</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            Obtén tu Excel de dos pestañas listo para tu ERP o software contable.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Banks Supported Section -->
        <section id="banks" class="py-20 px-6 bg-slate-900/50">
            <div class="max-w-5xl mx-auto text-center">
                <h2 class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent mb-12">
                    Formatos y Bancos Soportados
                </h2>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="p-6 rounded-xl glassmorphism flex flex-col justify-center items-center">
                        <span class="text-lg font-bold text-slate-300">BBVA</span>
                        <span class="text-xs text-blue-400 mt-2 font-medium">CH / TC / US (Dólares)</span>
                    </div>
                    <div class="p-6 rounded-xl glassmorphism flex flex-col justify-center items-center">
                        <span class="text-lg font-bold text-slate-300">AMERICAN EXPRESS</span>
                        <span class="text-xs text-violet-400 mt-2 font-medium">TC (Tarjetas)</span>
                    </div>
                    <div class="p-6 rounded-xl glassmorphism flex flex-col justify-center items-center">
                        <span class="text-lg font-bold text-slate-300">CITIBANAMEX</span>
                        <span class="text-xs text-emerald-400 mt-2 font-medium">CH (Cheques)</span>
                    </div>
                    <div class="p-6 rounded-xl glassmorphism flex flex-col justify-center items-center">
                        <span class="text-lg font-bold text-slate-300">SCOTIABANK</span>
                        <span class="text-xs text-amber-400 mt-2 font-medium">CH (Cheques)</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer Section -->
        <footer class="py-12 border-t border-slate-800 text-center text-slate-500 text-sm">
            <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <span>&copy; {{ date('Y') }} FilaConcilia. Todos los derechos reservados.</span>
                <span class="text-slate-400">Desarrollado con Laravel, PHP 8.4 y FilamentPHP</span>
            </div>
        </footer>

    </body>
</html>
