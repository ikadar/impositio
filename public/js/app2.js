const stage = new Konva.Stage({
    container: 'konva-container', // id of container <div>
    width: window.innerWidth,
    height: 1400
    // height: window.innerHeight
});

const scale = 0.8;

let baseLayer = new Konva.Layer({
    x: 0,
    y: 0,
    id: "base",
    scaleX: scale,
    scaleY: scale,
});
stage.add(baseLayer);

const show = (input, group) => {

    const rectGroup = showRect({
        id: input.id,
        color: input.color || "black",
        x: input.x,
        y: input.y,
        width: input.width,
        height: input.height,
    }, group)

    showChildren(input, input.children, rectGroup);

}

const showRect = (rectData, group) => {

    const rectGroup = new Konva.Group({
        id: `${rectData.id}-group`,
        x: rectData.x,
        y: rectData.y,
    });
    group.add(rectGroup);

    console.log(rectData);
    const rect = new Konva.Rect({
        id: rectData.id,
        x: 0,
        y: 0,
        // x: rectData.x,
        // y: rectData.y,
        width: rectData.width,
        height: rectData.height,
        // fill: 'white',
        stroke: rectData.color,
        // stroke: 'black',
        strokeWidth: 1
    });
    rectGroup.add(rect);

    const label = new Konva.Text({
        x: 0,
        y: 0,
        // width: options.length,
        // height: 20,
        align: "left",
        // verticalAlign: "middle",
        text: rectData.id,
        fontSize: 12,
        fontFamily: 'Helvetica Neue',
        fill: 'black',
    });
    rectGroup.add(label);

    return rectGroup;
}

const showChildren = (parent, children, group) => {

    for (let i in children) {
        show(children[i], group)
    }
}

const showData = () => {
    fetch('/test2', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: "[]"
        // body: JSON.stringify(input)
    })
        .then(response => response.json())
        .then(data => {
            show(data, baseLayer);
        })
        .catch(error => {
            console.error('Error loading JSON:', error);
        });

}


// 👇 Expose it globally
window.showData = showData;