<?php

return function (string $theme, string $title, string $innerContent, string $locale): string {
    return <<<HTML
            <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>{$title}</title>
                    <link rel="icon" type="image/png" href="{$theme}/style/favicon.png">
                    <link rel="stylesheet" href="{$theme}/style/styles.css">
                </head>
                <body>
                    <div class="container">
                        <div class="background-image">
                            <picture>
                                <source srcset="{$theme}/style/background-720.jpg" media="(max-width: 720px)">
                                <source srcset="{$theme}/style/background-1280.jpg" media="(max-width: 1280px)">
                                <source srcset="{$theme}/style/background-1620.jpg" media="(max-width: 1620px)">
                                <source srcset="{$theme}/style/background-5224.jpg" media="(min-width: 1281px)">
                                <img src="{$theme}/style/background-1280.jpg" alt="Fondo" class="background-img" />
                            </picture>
                            <div class="overlay-content">
                                <img src="{$theme}/style/logo.png" alt="Logotipo" class="logo" />
                                <h2>Hello</h2>
                            </div>
                        </div>
                        <div class="form-content">
                            <div class="loading">
                            </div>
                            <div class="in-form">
                                {$innerContent}
                            </div>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const formContent = document.querySelector('.form-content');
                            const loadingIndicator = document.querySelector('.loading');
                            formContent.classList.add('slide-in');
                            setTimeout(function() {
                                const forms = formContent.querySelectorAll('form');
                                forms.forEach(form => {
                                    function handleFormSubmit(event) {
                                        event.preventDefault();
                                        formContent.classList.add('slide-out');
                                        loadingIndicator.style.display = 'block';
                                        setTimeout(function() {
                                            form.removeEventListener('submit', handleFormSubmit);
                                            form.submit();
                                            }, 500);
                                    }
                                    const existingSubmitListener = form.onsubmit;
                                    if (existingSubmitListener) {
                                        form.addEventListener('submit', function(event) {
                                            existingSubmitListener.call(form, event);
                                            handleFormSubmit(event);         
                                        });
                                    } else {
                                        form.addEventListener('submit', handleFormSubmit);
                                    }
                                });
                            }, 10);
                        }); 
                    </script>
                </body>
            </html>
        HTML;
};
