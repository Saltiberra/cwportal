/**
 * CleanWatts Portal - Particles.js Configuration
 * Animated background for login page and dashboards
 */

(function () {
    'use strict';

    // ============================================
    // PARTICLES CONFIGURATION PRESETS
    // ============================================

    const presets = {
        // Clean, professional particles for login
        login: {
            particles: {
                number: {
                    value: 60,
                    density: {
                        enable: true,
                        value_area: 800
                    }
                },
                color: {
                    value: ['#2CCCD3', '#26989D', '#1fb7b8', '#17a2a8']
                },
                shape: {
                    type: ['circle', 'triangle'],
                    stroke: {
                        width: 0,
                        color: '#000000'
                    }
                },
                opacity: {
                    value: 0.4,
                    random: true,
                    anim: {
                        enable: true,
                        speed: 1,
                        opacity_min: 0.1,
                        sync: false
                    }
                },
                size: {
                    value: 4,
                    random: true,
                    anim: {
                        enable: true,
                        speed: 2,
                        size_min: 1,
                        sync: false
                    }
                },
                line_linked: {
                    enable: true,
                    distance: 150,
                    color: '#2CCCD3',
                    opacity: 0.2,
                    width: 1
                },
                move: {
                    enable: true,
                    speed: 1.5,
                    direction: 'none',
                    random: true,
                    straight: false,
                    out_mode: 'out',
                    bounce: false,
                    attract: {
                        enable: true,
                        rotateX: 600,
                        rotateY: 1200
                    }
                }
            },
            interactivity: {
                detect_on: 'canvas',
                events: {
                    onhover: {
                        enable: true,
                        mode: 'grab'
                    },
                    onclick: {
                        enable: true,
                        mode: 'push'
                    },
                    resize: true
                },
                modes: {
                    grab: {
                        distance: 140,
                        line_linked: {
                            opacity: 0.5
                        }
                    },
                    push: {
                        particles_nb: 3
                    },
                    remove: {
                        particles_nb: 2
                    }
                }
            },
            retina_detect: true
        },

        // More subtle particles for dashboard backgrounds
        dashboard: {
            particles: {
                number: {
                    value: 30,
                    density: {
                        enable: true,
                        value_area: 1000
                    }
                },
                color: {
                    value: '#2CCCD3'
                },
                shape: {
                    type: 'circle'
                },
                opacity: {
                    value: 0.15,
                    random: true
                },
                size: {
                    value: 3,
                    random: true
                },
                line_linked: {
                    enable: true,
                    distance: 200,
                    color: '#2CCCD3',
                    opacity: 0.1,
                    width: 1
                },
                move: {
                    enable: true,
                    speed: 0.8,
                    direction: 'none',
                    random: true,
                    out_mode: 'out'
                }
            },
            interactivity: {
                detect_on: 'canvas',
                events: {
                    onhover: {
                        enable: false
                    },
                    onclick: {
                        enable: false
                    },
                    resize: true
                }
            },
            retina_detect: true
        },

        // Bubbles effect
        bubbles: {
            particles: {
                number: {
                    value: 40,
                    density: {
                        enable: true,
                        value_area: 800
                    }
                },
                color: {
                    value: ['#2CCCD3', '#26989D', '#ffffff']
                },
                shape: {
                    type: 'circle'
                },
                opacity: {
                    value: 0.3,
                    random: true,
                    anim: {
                        enable: true,
                        speed: 0.5,
                        opacity_min: 0.05,
                        sync: false
                    }
                },
                size: {
                    value: 20,
                    random: true,
                    anim: {
                        enable: true,
                        speed: 3,
                        size_min: 5,
                        sync: false
                    }
                },
                line_linked: {
                    enable: false
                },
                move: {
                    enable: true,
                    speed: 1,
                    direction: 'top',
                    random: true,
                    straight: false,
                    out_mode: 'out'
                }
            },
            interactivity: {
                detect_on: 'canvas',
                events: {
                    onhover: {
                        enable: true,
                        mode: 'bubble'
                    },
                    onclick: {
                        enable: true,
                        mode: 'repulse'
                    },
                    resize: true
                },
                modes: {
                    bubble: {
                        distance: 100,
                        size: 30,
                        duration: 2,
                        opacity: 0.5
                    },
                    repulse: {
                        distance: 200,
                        duration: 0.4
                    }
                }
            },
            retina_detect: true
        },

        // Network/connections theme
        network: {
            particles: {
                number: {
                    value: 80,
                    density: {
                        enable: true,
                        value_area: 700
                    }
                },
                color: {
                    value: '#2CCCD3'
                },
                shape: {
                    type: 'circle'
                },
                opacity: {
                    value: 0.6,
                    random: false
                },
                size: {
                    value: 2,
                    random: true
                },
                line_linked: {
                    enable: true,
                    distance: 120,
                    color: '#2CCCD3',
                    opacity: 0.4,
                    width: 1
                },
                move: {
                    enable: true,
                    speed: 2,
                    direction: 'none',
                    random: false,
                    straight: false,
                    out_mode: 'bounce'
                }
            },
            interactivity: {
                detect_on: 'canvas',
                events: {
                    onhover: {
                        enable: true,
                        mode: 'repulse'
                    },
                    onclick: {
                        enable: true,
                        mode: 'push'
                    },
                    resize: true
                },
                modes: {
                    repulse: {
                        distance: 100,
                        duration: 0.4
                    },
                    push: {
                        particles_nb: 4
                    }
                }
            },
            retina_detect: true
        },

        // Snow effect (for winter themes)
        snow: {
            particles: {
                number: {
                    value: 100,
                    density: {
                        enable: true,
                        value_area: 800
                    }
                },
                color: {
                    value: '#ffffff'
                },
                shape: {
                    type: 'circle'
                },
                opacity: {
                    value: 0.7,
                    random: true
                },
                size: {
                    value: 5,
                    random: true
                },
                line_linked: {
                    enable: false
                },
                move: {
                    enable: true,
                    speed: 2,
                    direction: 'bottom',
                    random: true,
                    straight: false,
                    out_mode: 'out'
                }
            },
            interactivity: {
                detect_on: 'canvas',
                events: {
                    onhover: {
                        enable: true,
                        mode: 'bubble'
                    },
                    onclick: {
                        enable: false
                    },
                    resize: true
                },
                modes: {
                    bubble: {
                        distance: 200,
                        size: 10,
                        duration: 2,
                        opacity: 0.8
                    }
                }
            },
            retina_detect: true
        }
    };

    // ============================================
    // INITIALIZATION FUNCTION
    // ============================================

    function initParticles(elementId, presetName = 'login') {
        // Check if particles.js is loaded
        if (typeof particlesJS === 'undefined') {
            console.warn('Particles.js not loaded');
            return false;
        }

        // Check if element exists
        const element = document.getElementById(elementId);
        if (!element) {
            console.warn('Particles container not found:', elementId);
            return false;
        }

        // Get preset configuration
        const config = presets[presetName] || presets.login;

        // Initialize particles
        particlesJS(elementId, config);

        console.log('Particles.js initialized with preset:', presetName);
        return true;
    }

    // ============================================
    // AUTO-INITIALIZE ON PAGE LOAD
    // ============================================

    function autoInit() {
        // Look for particles containers with data attributes
        const containers = document.querySelectorAll('[data-particles]');

        containers.forEach(container => {
            const id = container.id;
            const preset = container.dataset.particles || 'login';

            if (id) {
                initParticles(id, preset);
            }
        });

        // Auto-init for login page
        if (document.getElementById('particles-js')) {
            initParticles('particles-js', 'login');
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }

    // ============================================
    // PUBLIC API
    // ============================================

    window.CWParticles = {
        init: initParticles,
        presets: presets,

        // Helper to create particles container
        createContainer: function (parentElement, id = 'particles-js', preset = 'login') {
            const container = document.createElement('div');
            container.id = id;
            container.style.cssText = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none;';

            if (typeof parentElement === 'string') {
                parentElement = document.querySelector(parentElement);
            }

            if (parentElement) {
                parentElement.style.position = 'relative';
                parentElement.insertBefore(container, parentElement.firstChild);

                // Allow pointer events on particles
                container.style.pointerEvents = 'auto';

                setTimeout(() => initParticles(id, preset), 100);
                return container;
            }

            return null;
        },

        // Destroy particles
        destroy: function (elementId) {
            if (typeof pJSDom !== 'undefined' && pJSDom.length > 0) {
                // Find and remove the specific pJS instance
                pJSDom = pJSDom.filter(pjs => {
                    if (pjs.pJS && pjs.pJS.canvas && pjs.pJS.canvas.el.id === elementId + '-canvas') {
                        pjs.pJS.fn.vendors.destroypJS();
                        return false;
                    }
                    return true;
                });
            }
        }
    };

})();
