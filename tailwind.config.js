import forms from "@tailwindcss/forms";
export default {
    content: ["./resources/views/**/*.blade.php", "./resources/js/**/*.js"],
    theme: {
        extend: {
            fontFamily: {
                sans: ["Georgia", "Times New Roman", "serif"],
                serif: ["Georgia", "Times New Roman", "serif"],
            },
        },
    },
    plugins: [forms],
};
